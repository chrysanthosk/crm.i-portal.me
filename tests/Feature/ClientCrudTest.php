<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function unprivileged(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function clientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'        => 'Jane',
            'last_name'         => 'Doe',
            'dob'               => '1990-06-15',
            'mobile'            => '+35799000001',
            'email'             => 'jane.doe@example.com',
            'gender'            => 'Female',
            'registration_date' => '2026-01-01',
            'address'           => '1 Test St',
            'city'              => 'Limassol',
            'notes'             => 'Test note',
            'comments'          => 'Test comment',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_view_clients_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('clients.index'))
            ->assertOk();
    }

    public function test_unprivileged_user_cannot_view_clients(): void
    {
        $this->actingAs($this->unprivileged())
            ->get(route('clients.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Create / Store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_client(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('clients.store'), $this->clientPayload())
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'email'      => 'jane.doe@example.com',
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ]);
    }

    public function test_store_requires_first_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('clients.store'), $this->clientPayload(['first_name' => '']))
            ->assertSessionHasErrors('first_name');
    }

    public function test_store_requires_valid_gender(): void
    {
        $this->actingAs($this->admin())
            ->post(route('clients.store'), $this->clientPayload(['gender' => 'Robot']))
            ->assertSessionHasErrors('gender');
    }

    public function test_store_rejects_notes_exceeding_max_length(): void
    {
        $this->actingAs($this->admin())
            ->post(route('clients.store'), $this->clientPayload(['notes' => str_repeat('x', 5001)]))
            ->assertSessionHasErrors('notes');
    }

    public function test_store_rejects_comments_exceeding_max_length(): void
    {
        $this->actingAs($this->admin())
            ->post(route('clients.store'), $this->clientPayload(['comments' => str_repeat('x', 5001)]))
            ->assertSessionHasErrors('comments');
    }

    // -------------------------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_client(): void
    {
        $client = Client::create($this->clientPayload());

        $this->actingAs($this->admin())
            ->put(route('clients.update', $client), $this->clientPayload(['first_name' => 'Updated']))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'first_name' => 'Updated']);
    }

    public function test_update_rejects_notes_exceeding_max_length(): void
    {
        $client = Client::create($this->clientPayload());

        $this->actingAs($this->admin())
            ->put(route('clients.update', $client), $this->clientPayload(['notes' => str_repeat('y', 5001)]))
            ->assertSessionHasErrors('notes');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_client(): void
    {
        $client = Client::create($this->clientPayload());

        $this->actingAs($this->admin())
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    public function test_export_returns_csv(): void
    {
        Client::create($this->clientPayload());

        $response = $this->actingAs($this->admin())
            ->get(route('clients.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    public function test_import_creates_new_clients_from_csv(): void
    {
        $csv = implode("\n", [
            'registration_date,first_name,last_name,dob,mobile,email,address,city,gender,notes,comments',
            '2026-01-01,Import,User,1985-03-10,+35799111111,import.user@example.com,Addr,City,Male,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('clients.import'), ['file' => $file])
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['email' => 'import.user@example.com']);
    }

    public function test_import_updates_existing_client_by_email(): void
    {
        Client::create($this->clientPayload(['email' => 'existing@example.com', 'first_name' => 'Old']));

        $csv = implode("\n", [
            'registration_date,first_name,last_name,dob,mobile,email,address,city,gender,notes,comments',
            '2026-01-01,New,Name,1985-03-10,+35799222222,existing@example.com,Addr,City,Male,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('clients.import'), ['file' => $file]);

        $this->assertDatabaseHas('clients', ['email' => 'existing@example.com', 'first_name' => 'New']);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_import_rejects_non_csv_file(): void
    {
        $file = UploadedFile::fake()->create('clients.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin())
            ->post(route('clients.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
