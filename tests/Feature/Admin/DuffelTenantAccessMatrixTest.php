<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DuffelTenantAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_view_duffel_row_in_tenant_provider_access_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create([
            'name' => 'Duffel Governance Tenant',
            'slug' => 'duffel-governance-tenant',
            'plan_tier' => 'enterprise',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tenants.providers.edit', $tenant))
            ->assertOk()
            ->assertSee('duffel')
            ->assertSee('quota soft/hard limits', false);
    }

    public function test_super_admin_can_store_duffel_operation_priority_fallback_and_quota_controls(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create([
            'name' => 'Duffel Override Tenant',
            'slug' => 'duffel-override-tenant',
            'plan_tier' => 'growth',
        ]);

        $payload = [
            'rows' => [
                [
                    'provider' => 'duffel',
                    'plan_code' => 'growth',
                    'is_enabled' => 1,
                    'can_search' => 1,
                    'can_price' => 1,
                    'can_book' => 1,
                    'allow_multi_provider' => 1,
                    'allow_fallback' => 0,
                    'priority_order' => 5,
                    'bookings_monthly_quota' => 150,
                    'searches_daily_quota' => 1200,
                    'soft_limit_percent' => 70,
                    'hard_limit_enforced' => 1,
                    'overage_alert_enabled' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->put(route('admin.tenants.providers.update', $tenant), $payload);

        $response->assertRedirect(route('admin.tenants.providers.edit', $tenant));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_provider_access', [
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'is_enabled' => 1,
            'can_search' => 1,
            'can_price' => 1,
            'can_book' => 1,
            'allow_multi_provider' => 1,
            'allow_fallback' => 0,
            'priority_order' => 5,
        ]);

        if (Schema::hasColumn('tenant_provider_access', 'soft_limit_percent')) {
            $this->assertDatabaseHas('tenant_provider_access', [
                'tenant_id' => $tenant->id,
                'provider' => 'duffel',
                'soft_limit_percent' => 70,
            ]);
        }
        if (Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced')) {
            $this->assertDatabaseHas('tenant_provider_access', [
                'tenant_id' => $tenant->id,
                'provider' => 'duffel',
                'hard_limit_enforced' => 1,
            ]);
        }
        if (Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled')) {
            $this->assertDatabaseHas('tenant_provider_access', [
                'tenant_id' => $tenant->id,
                'provider' => 'duffel',
                'overage_alert_enabled' => 1,
            ]);
        }
    }
}
