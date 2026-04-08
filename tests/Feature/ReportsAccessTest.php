<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAccessTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_admin_can_access_analytics(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.analytics'))
            ->assertOk();
    }

    public function test_admin_can_access_staff_performance(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.staff_performance'))
            ->assertOk();
    }

    public function test_admin_can_access_reports_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_unprivileged_user_cannot_access_financial_reports(): void
    {
        $this->actingAs($this->unprivileged())
            ->get(route('reports.financial.income'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_reports(): void
    {
        $this->get(route('reports.analytics'))
            ->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Date range clamping — analytics
    // -------------------------------------------------------------------------

    public function test_analytics_accepts_normal_date_range(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.analytics', ['from' => '2026-01-01', 'to' => '2026-03-31']))
            ->assertOk();
    }

    public function test_analytics_handles_date_range_exceeding_366_days_without_error(): void
    {
        // Passes a 2-year span — clampRange() should silently cap it rather than crash.
        $this->actingAs($this->admin())
            ->get(route('reports.analytics', ['from' => '2024-01-01', 'to' => '2026-01-01']))
            ->assertOk();
    }

    public function test_analytics_handles_inverted_date_range_without_error(): void
    {
        // from > to — clampRange() normalises this to a single-day range.
        $this->actingAs($this->admin())
            ->get(route('reports.analytics', ['from' => '2026-06-01', 'to' => '2026-01-01']))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Date range clamping — staff performance
    // -------------------------------------------------------------------------

    public function test_staff_performance_handles_date_range_exceeding_366_days_without_error(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.staff_performance', ['from_date' => '2024-01-01', 'to_date' => '2026-01-01']))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // BI data endpoint smoke tests
    // -------------------------------------------------------------------------

    public function test_bi_yoy_returns_json(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.data', ['report' => 'yoy']))
            ->assertOk()
            ->assertJsonStructure([]);
    }

    public function test_bi_unknown_report_returns_400(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.data', ['report' => 'nonexistent_report']))
            ->assertStatus(400);
    }
}
