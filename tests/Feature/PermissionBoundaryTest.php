<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function nonPrivilegedUser(): User
    {
        return User::factory()->create([
            'role' => 'staff',
        ]);
    }

    public function test_non_privileged_user_cannot_access_settings_area(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/settings/configuration')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_access_smtp_settings(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/settings/smtp')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_access_bulk_sms(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/bulk-sms')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_access_pos(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/pos')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_access_financial_reports(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/reports/financial/income')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_access_gdpr_tools(): void
    {
        $user = $this->nonPrivilegedUser();

        $this->actingAs($user)
            ->get('/settings/gdpr')
            ->assertForbidden();
    }

    public function test_admin_can_access_settings_area(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get('/settings/configuration');

        $this->assertNotSame(403, $response->getStatusCode());
    }
}
