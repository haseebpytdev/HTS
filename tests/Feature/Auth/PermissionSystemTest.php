<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_matrix_exposes_module_permission(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->assertTrue($admin->hasPermission('module.analytics.view'));
        $this->assertFalse($admin->hasPermission('module.agency.dashboard.view'));
    }

    public function test_action_level_permission_matrix_blocks_sales_operator_for_delete_action(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::SALES_OPERATOR->value,
        ]);

        $this->assertFalse($sales->hasPermission('action.quotations.delete'));
    }

    public function test_action_level_permission_matrix_allows_admin_for_delete_action(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->assertTrue($admin->hasPermission('action.quotations.delete'));
    }

    public function test_module_permission_middleware_denies_when_matrix_omits_module(): void
    {
        config([
            'permissions.role_matrix.admin' => [
                'module.dashboard.view',
            ],
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();
    }
}
