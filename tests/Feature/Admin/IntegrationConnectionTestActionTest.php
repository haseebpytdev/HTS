<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationConnectionTestActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_test_connection_and_result_persists(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $tenant = Tenant::query()->create(['name' => 'T1', 'slug' => 't1', 'plan_tier' => 'pro']);

        $connection = IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'amadeus',
            'environment' => 'sandbox',
            'account_name' => 'Amadeus Sandbox',
            'name' => 'Amadeus Sandbox',
            'is_active' => true,
            'status' => 'untested',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $connection));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame('healthy', $connection->status);
        $this->assertNotNull($connection->last_tested_at);
        $this->assertNotNull($connection->last_success_at);
    }
}
