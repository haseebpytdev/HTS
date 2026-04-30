<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_view_permission_matrix_screen(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('admin.system.permissions.index'))
            ->assertOk()
            ->assertSee('Permission matrix');
    }

    public function test_non_super_admin_cannot_access_permission_matrix_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)
            ->get(route('admin.system.permissions.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_save_role_proposal_and_export(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->put(route('admin.system.permissions.update', 'sales_operator'), [
                'permissions' => [
                    'module.dashboard.view',
                    'module.analytics.view',
                ],
            ])
            ->assertRedirect(route('admin.system.permissions.index'));

        $raw = ApplicationSetting::getValue('permissions.role_matrix_proposed');
        $this->assertNotNull($raw);
        $this->assertStringContainsString('sales_operator', (string) $raw);

        $this->actingAs($superAdmin)
            ->get(route('admin.system.permissions.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('role_matrix')
            ->assertSee('sales_operator');
    }
}
