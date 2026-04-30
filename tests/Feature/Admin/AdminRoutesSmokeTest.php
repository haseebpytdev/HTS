<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class AdminRoutesSmokeTest extends TestCase
{
    public function test_super_admin_get_routes_do_not_return_http_500(): void
    {
        config([
            'permissions.role_matrix.super_admin' => ['*'],
        ]);

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($superAdmin);

        $criticalRoutes = [
            '/admin/dashboard',
            '/admin/operations-center',
            '/admin/cms/groups',
            '/admin/cms/packages',
            '/admin/integrations/search-results',
        ];

        foreach ($criticalRoutes as $uri) {
            $response = $this->get($uri);

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                'HTTP 500 for admin route: '.$uri
            );
        }
    }
}
