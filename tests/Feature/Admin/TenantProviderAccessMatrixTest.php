<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProviderAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_view_access_matrix_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create(['name' => 'Tenant Matrix', 'slug' => 'tenant-matrix', 'plan_tier' => 'pro']);

        $this->actingAs($admin)
            ->get(route('admin.integrations.access-matrix.index'))
            ->assertOk()
            ->assertSee($tenant->name);
    }

    public function test_super_admin_can_update_tenant_provider_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create(['name' => 'Tenant Override', 'slug' => 'tenant-override', 'plan_tier' => 'growth']);

        $payload = [
            'rows' => [
                [
                    'provider' => 'travelport',
                    'environment' => 'sandbox',
                    'plan_code' => 'growth',
                    'is_enabled' => 1,
                    'can_search' => 1,
                    'can_price' => 1,
                    'can_book' => 0,
                    'allow_multi_provider' => 1,
                    'allow_fallback' => 1,
                    'priority_order' => 10,
                ],
                [
                    'provider' => 'amadeus',
                    'environment' => 'production',
                    'plan_code' => 'growth',
                    'is_enabled' => 1,
                    'can_search' => 1,
                    'can_price' => 1,
                    'can_book' => 1,
                    'allow_multi_provider' => 1,
                    'allow_fallback' => 1,
                    'priority_order' => 20,
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->put(route('admin.integrations.access-matrix.update', $tenant), $payload);

        $response->assertRedirect(route('admin.integrations.access-matrix.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_provider_access', [
            'tenant_id' => $tenant->id,
            'provider' => 'travelport',
            'environment' => 'sandbox',
            'is_enabled' => 1,
            'can_search' => 1,
            'can_price' => 1,
            'can_book' => 0,
            'allow_multi_provider' => 1,
            'allow_fallback' => 1,
            'priority_order' => 10,
        ]);
    }
}
