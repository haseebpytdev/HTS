<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Admin\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_core_dashboard_sections(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Action Queue')
            ->assertSee('Quick Controls')
            ->assertSee('Health Center')
            ->assertSee('Recent Supplier Test Failures')
            ->assertSee('Super Admin Operations Center');
    }

    public function test_admin_with_limited_permissions_only_sees_allowed_widgets(): void
    {
        config([
            'permissions.role_matrix.admin' => [
                'module.dashboard.view',
                'module.bookings.view',
            ],
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Action Queue')
            ->assertSee('Recent Bookings')
            ->assertDontSee('Module & Integration Live Management')
            ->assertDontSee('Current Month vs Last Month')
            ->assertDontSee('Recent Integration Test Failures');
    }

    public function test_dashboard_kpis_prefer_summary_kpis_over_overview_kpi_fallback(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $summary = \Mockery::mock(DashboardSummaryService::class);
        $summary->shouldReceive('summary')
            ->once()
            ->andReturn([
                'range' => ['from_date' => '2026-04-01', 'to_date' => '2026-04-30'],
                'kpis' => [
                    'total_bookings' => 111111,
                    'total_users' => 222222,
                    'this_month_bookings' => 333333,
                    'this_month_revenue' => 444444.44,
                    'pending_refunds' => 1,
                    'pending_deposits' => 2,
                    'failed_payments' => 3,
                    'pending_support_tickets' => 4,
                ],
                'overview' => [
                    'kpi' => [
                        'total_bookings' => 999999,
                        'total_users' => 999999,
                        'this_month_bookings' => 999999,
                        'this_month_revenue' => 999999.99,
                        'pending_refunds' => 9,
                        'pending_deposits' => 9,
                        'failed_payments' => 9,
                        'pending_support_tickets' => 9,
                    ],
                    'action_queue' => [],
                    'performance' => [],
                    'live_management' => [],
                    'recent' => [],
                ],
                'trends' => ['weekly' => [], 'monthly' => []],
                'quick_actions' => [],
                'widget_visibility' => [
                    'show_action_queue' => true,
                    'show_live_management' => true,
                    'show_performance' => true,
                    'show_recent_bookings' => true,
                    'show_recent_support' => true,
                    'show_recent_integrations' => true,
                    'show_recent_exports' => true,
                    'show_cms_links' => true,
                ],
            ]);
        $this->app->instance(DashboardSummaryService::class, $summary);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Super Admin Operations Center')
            ->assertDontSee('999999');
    }
}
