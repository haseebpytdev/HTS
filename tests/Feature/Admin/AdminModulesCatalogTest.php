<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ServiceModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulesCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_catalog_filters_by_service_type(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        // Hit the page once to trigger seed via ModuleCatalogService::ensureSeeded().
        $this->actingAs($superAdmin)->get(route('admin.modules.index'))->assertOk();

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.modules.index', ['service_type' => 'Flights']))
            ->assertOk();

        $response->assertSee('Travelport');
        $response->assertDontSee('Visa Desk');
    }

    public function test_modules_catalog_filters_by_status_active(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($superAdmin)->get(route('admin.modules.index'))->assertOk();

        ServiceModule::query()->where('code', 'travelport_flights')->update(['is_active' => true]);
        ServiceModule::query()->where('code', 'visa_processing')->update(['is_active' => false]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.modules.index', ['status' => 'active']))
            ->assertOk();

        $response->assertSee('Travelport');
        $response->assertDontSee('Visa Desk');
    }

    public function test_modules_catalog_hides_actions_when_permissions_are_missing(): void
    {
        config([
            'permissions.role_matrix.super_admin' => [
                'module.dashboard.view',
                'module.settings.view',
            ],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.modules.index'))
            ->assertOk();

        $response->assertSee('Settings');
        $response->assertDontSee('Save Toggle');
        $response->assertDontSee('Connections');
    }
}
