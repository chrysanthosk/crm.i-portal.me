<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use App\Models\VatType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function unprivileged(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    /**
     * Build the minimal related records needed for an appointment.
     * Returns [staff, service, category].
     */
    private function scaffoldAppointmentDeps(): array
    {
        $vatType  = VatType::create(['name' => 'Standard', 'vat_percent' => 19.00]);
        $category = ServiceCategory::create(['name' => 'Hair', 'description' => '']);
        $service  = Service::create([
            'name'        => 'Haircut',
            'category_id' => $category->id,
            'price'       => 20.00,
            'vat_type_id' => $vatType->id,
            'duration'    => 30,
        ]);
        $staffUser = User::factory()->create(['role' => 'user']);
        $staff     = Staff::create(['user_id' => $staffUser->id]);

        return [$staff, $service, $category];
    }

    private function appointmentPayload(int $staffId, int $serviceId, int $categoryId, array $overrides = []): array
    {
        return array_merge([
            'staff_id'            => $staffId,
            'start_at'            => '2026-06-01T10:00',
            'end_at'              => '2026-06-01T10:30',
            'client_first_name'   => 'Walk',
            'client_last_name'    => 'In',
            'client_phone'        => '',
            'service_category_id' => $categoryId,
            'service_id'          => $serviceId,
            'status'              => 'scheduled',
            'send_sms'            => false,
            'notes'               => '',
            'internal_notes'      => '',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_view_appointments_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('appointments.index'))
            ->assertOk();
    }

    public function test_unprivileged_user_cannot_view_appointments(): void
    {
        $this->actingAs($this->unprivileged())
            ->get(route('appointments.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_appointment_with_walk_in_client(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $this->actingAs($this->admin())
            ->post(route('appointments.store'), $this->appointmentPayload($staff->id, $service->id, $category->id))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('appointments', [
            'staff_id'   => $staff->id,
            'service_id' => $service->id,
            'status'     => 'scheduled',
        ]);
    }

    public function test_admin_can_create_appointment_with_existing_client(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $client = Client::create([
            'first_name' => 'Maria',
            'last_name'  => 'Test',
            'dob'        => '1990-01-01',
            'mobile'     => '+35799000001',
            'email'      => 'maria@example.com',
            'gender'     => 'Female',
        ]);

        $payload = $this->appointmentPayload($staff->id, $service->id, $category->id, [
            'client_id'          => $client->id,
            'client_first_name'  => null,
            'client_last_name'   => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('appointments.store'), $payload)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'status'    => 'scheduled',
        ]);
    }

    public function test_store_requires_staff_id(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $this->actingAs($this->admin())
            ->post(route('appointments.store'), $this->appointmentPayload($staff->id, $service->id, $category->id, ['staff_id' => '']))
            ->assertSessionHasErrors('staff_id');
    }

    public function test_store_requires_service_belonging_to_category(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $otherCategory = ServiceCategory::create(['name' => 'Nails', 'description' => '']);

        $this->actingAs($this->admin())
            ->post(route('appointments.store'), $this->appointmentPayload($staff->id, $service->id, $otherCategory->id))
            ->assertSessionHasErrors('service_id');
    }

    public function test_store_rejects_notes_exceeding_max_length(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $this->actingAs($this->admin())
            ->post(route('appointments.store'), $this->appointmentPayload($staff->id, $service->id, $category->id, [
                'notes' => str_repeat('x', 2001),
            ]))
            ->assertSessionHasErrors('notes');
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_appointment_status(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $appointment = Appointment::create([
            'staff_id'   => $staff->id,
            'service_id' => $service->id,
            'start_at'   => '2026-06-01 10:00:00',
            'end_at'     => '2026-06-01 10:30:00',
            'status'     => 'scheduled',
        ]);

        $this->actingAs($this->admin())
            ->put(route('appointments.update', $appointment), $this->appointmentPayload($staff->id, $service->id, $category->id, [
                'status' => 'confirmed',
            ]))
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
    }

    // -------------------------------------------------------------------------
    // Move (drag-and-drop calendar)
    // -------------------------------------------------------------------------

    public function test_admin_can_move_appointment(): void
    {
        [$staff, $service, $category] = $this->scaffoldAppointmentDeps();

        $appointment = Appointment::create([
            'staff_id'   => $staff->id,
            'service_id' => $service->id,
            'start_at'   => '2026-06-01 10:00:00',
            'end_at'     => '2026-06-01 10:30:00',
            'status'     => 'scheduled',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('appointments.move', $appointment), [
                'start_at' => '2026-06-02T14:00',
                'end_at'   => '2026-06-02T14:30',
                'staff_id' => $staff->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('appointments', [
            'id'       => $appointment->id,
            'start_at' => '2026-06-02 14:00:00',
        ]);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_appointment(): void
    {
        [$staff, $service] = $this->scaffoldAppointmentDeps();

        $appointment = Appointment::create([
            'staff_id'   => $staff->id,
            'service_id' => $service->id,
            'start_at'   => '2026-06-01 10:00:00',
            'end_at'     => '2026-06-01 10:30:00',
            'status'     => 'scheduled',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('appointments.destroy', $appointment))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }
}
