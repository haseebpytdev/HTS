<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationConnectionIndexManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_test_environment_tab_filters_include_test_aliases(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create([
            'name' => 'Filter Tenant',
            'slug' => 'filter-tenant',
            'plan_tier' => 'pro',
        ]);

        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Sandbox Connection',
            'name' => 'Sandbox Connection',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'test',
            'account_name' => 'Test Connection',
            'name' => 'Test Connection',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'production',
            'account_name' => 'Production Connection',
            'name' => 'Production Connection',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.integrations.index', [
            'environment' => 'sandbox',
        ]));

        $response->assertOk();
        $response->assertSee('Sandbox Connection');
        $response->assertSee('Test Connection');
        $response->assertDontSee('Production Connection');
        $response->assertSee('Tenant ID: '.$tenant->id);
    }

    public function test_super_admin_can_move_connection_to_trash(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Trash Me',
            'name' => 'Trash Me',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.integrations.destroy', $connection));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Connection moved to trash.');
        $this->assertSoftDeleted('integration_connections', [
            'id' => $connection->id,
        ]);
    }
}
