<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuffelConnectionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_super_admin_can_save_duffel_token_encrypted_and_it_is_not_redisplayed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.store'), [
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Test Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => '1',
            'is_default' => '1',
            'supported_operations' => ['search', 'pricing', 'booking'],
            'credential_owner' => 'Flights team',
            'module_notes' => 'Sandbox token for smoke tests',
            'credentials' => [
                'api_token' => 'duffel_test_example_secure_token',
            ],
        ]);

        $response->assertRedirect();

        $connection = IntegrationConnection::query()->firstOrFail();
        $this->assertSame('duffel', $connection->provider);
        $this->assertSame('sandbox', $connection->environment);
        $this->assertSame('Flights team', $connection->config['credential_owner'] ?? null);
        $this->assertSame('Sandbox token for smoke tests', $connection->config['module_notes'] ?? null);

        $token = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'api_token')
            ->firstOrFail();
        $this->assertSame('duffel_test_example_secure_token', $token->credential_value_encrypted);

        $rawToken = DB::table('integration_credentials')
            ->where('id', $token->id)
            ->value('credential_value_encrypted');
        $this->assertNotSame('duffel_test_example_secure_token', $rawToken);

        $editPage = $this->actingAs($admin)->get(route('admin.integrations.edit', $connection));
        $editPage->assertOk();
        $editPage->assertDontSee('duffel_test_example_secure_token');
        $editPage->assertSee('Stored securely');
    }

    public function test_updating_duffel_connection_with_blank_token_keeps_existing_encrypted_value(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Test Token',
            'name' => 'Duffel Test Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);

        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_existing_token',
            'is_secret' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.integrations.update', $connection), [
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Test Token Updated',
            'base_url' => 'https://api.duffel.com',
            'is_active' => '1',
            'is_default' => '0',
            'supported_operations' => ['search', 'pricing', 'booking'],
            'credentials' => [
                'api_token' => '',
            ],
        ]);

        $response->assertRedirect();

        $storedToken = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'api_token')
            ->firstOrFail();
        $this->assertSame('duffel_test_existing_token', $storedToken->credential_value_encrypted);
    }

    public function test_updating_duffel_connection_with_masked_token_keeps_existing_value_and_test_uses_stored_token(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Masked Token',
            'name' => 'Duffel Masked Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_existing_masked_token',
            'is_secret' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.integrations.update', $connection), [
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Masked Token Updated',
            'base_url' => 'https://api.duffel.com',
            'is_active' => '1',
            'is_default' => '0',
            'supported_operations' => ['search', 'pricing', 'booking'],
            'credentials' => [
                'api_token' => '••••••••',
            ],
        ])->assertRedirect();

        $storedToken = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'api_token')
            ->firstOrFail();
        $this->assertSame('duffel_test_existing_masked_token', $storedToken->credential_value_encrypted);

        Http::fake([
            'https://api.duffel.com/air/airlines?limit=1' => Http::response([
                'data' => [['id' => 'airline_1']],
            ], 200),
        ]);
        $this->actingAs($admin)->post(route('admin.integrations.test', $connection))->assertRedirect();
        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.duffel.com/air/airlines?limit=1'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_existing_masked_token');
        });
    }

    public function test_duffel_test_connection_calls_duffel_api_and_persists_success_health_fields(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');
        config()->set('integrations.use_database_credentials', true);
        config()->set('duffel.credentials.test.api_token', 'duffel_test_config_fallback_token');

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Test Token',
            'name' => 'Duffel Test Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_valid_token',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.duffel.com/air/airlines?limit=1' => Http::response([
                'data' => [
                    ['id' => 'airline_1', 'name' => 'Demo Airline'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $connection));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.duffel.com/air/airlines?limit=1'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_valid_token')
                && $request->hasHeader('Duffel-Version', 'v2');
        });

        $connection->refresh();
        $this->assertSame('healthy', $connection->status);
        $this->assertNotNull($connection->last_tested_at);
        $this->assertNotNull($connection->last_success_at);
        $this->assertNull($connection->last_failure_reason);
    }

    public function test_duffel_test_connection_fails_when_real_search_path_would_select_a_different_connection(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');
        config()->set('integrations.use_database_credentials', true);

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $selectedConnection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Selected Duffel Token',
            'name' => 'Selected Duffel Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $selectedConnection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_selected_runtime_token',
            'is_secret' => true,
        ]);

        $testedConnection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Secondary Duffel Token',
            'name' => 'Secondary Duffel Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'is_default' => false,
            'status' => 'healthy',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $testedConnection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_secondary_token',
            'is_secret' => true,
        ]);

        Http::fake();

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $testedConnection));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        Http::assertNothingSent();

        $testedConnection->refresh();
        $this->assertSame('failed', $testedConnection->status);
        $this->assertStringContainsString(
            'same credential record selected by the real search path',
            (string) $testedConnection->last_failure_reason
        );
    }

    public function test_duffel_sandbox_connection_rejects_non_test_token_and_records_failure_reason(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Wrong Token',
            'name' => 'Duffel Wrong Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'live_token_not_allowed_in_test',
            'is_secret' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $connection));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $connection->refresh();
        $this->assertSame('failed', $connection->status);
        $this->assertNotNull($connection->last_tested_at);
        $this->assertNotNull($connection->last_failure_at);
        $this->assertStringContainsString('duffel_test_', (string) $connection->last_failure_reason);
    }

    public function test_duffel_connection_test_fails_when_duffel_client_auth_path_rejects_token(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');
        config()->set('integrations.use_database_credentials', true);

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'duffel',
            'environment' => 'sandbox',
            'account_name' => 'Duffel Unauthorized Token',
            'name' => 'Duffel Unauthorized Token',
            'base_url' => 'https://api.duffel.com',
            'is_active' => true,
            'is_default' => true,
            'status' => 'healthy',
            'supported_operations' => ['search', 'pricing', 'booking'],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_rejected_token',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.duffel.com/air/airlines?limit=1' => Http::response([
                'errors' => [[
                    'code' => 'access_token_not_found',
                    'message' => 'Access token not found.',
                ]],
            ], 401),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.test', $connection));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.duffel.com/air/airlines?limit=1'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_rejected_token')
                && $request->hasHeader('Duffel-Version', 'v2');
        });

        $connection->refresh();
        $this->assertSame('failed', $connection->status);
        $this->assertStringContainsString('HTTP 401', (string) $connection->last_failure_reason);
    }

    public function test_supplier_account_duffel_can_save_without_client_secret_and_test_with_stored_api_token(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin)->post(route('admin.integrations.accounts.store'), [
            'name' => 'Duffel Supplier Account',
            'provider' => 'duffel',
            'ownership_type' => 'platform_owner',
            'test' => [
                'base_url' => 'https://api.duffel.com',
                'client_id' => 'duffel_test_supplier_account_token',
                'is_active' => 1,
            ],
            'production' => [
                'is_active' => 0,
            ],
            'default_environment' => 'test',
        ])->assertRedirect();

        $testConnection = IntegrationConnection::query()
            ->where('provider', 'duffel')
            ->where('environment', 'test')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull(IntegrationCredential::query()
            ->where('integration_connection_id', $testConnection->id)
            ->where('credential_key', 'api_token')
            ->first());
        $this->assertNull(IntegrationCredential::query()
            ->where('integration_connection_id', $testConnection->id)
            ->where('credential_key', 'client_secret')
            ->first());

        Http::fake([
            'https://api.duffel.com/air/airlines?limit=1' => Http::response(['data' => [['id' => 'airline_1']]], 200),
        ]);
        $this->actingAs($admin)->post(route('admin.integrations.accounts.test-connection'), [
            'connection_id' => $testConnection->id,
        ])->assertRedirect();
        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.duffel.com/air/airlines?limit=1'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_supplier_account_token');
        });
    }

    public function test_supplier_account_update_with_masked_duffel_token_reuses_stored_value_for_retest(): void
    {
        config()->set('duffel.health_check_path', '/air/airlines?limit=1');
        config()->set('duffel.version', 'v2');

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin)->post(route('admin.integrations.accounts.store'), [
            'name' => 'Duffel Supplier Account Masked',
            'provider' => 'duffel',
            'ownership_type' => 'platform_owner',
            'test' => [
                'base_url' => 'https://api.duffel.com',
                'client_id' => 'duffel_test_supplier_masked_token',
                'is_active' => 1,
            ],
            'production' => ['is_active' => 0],
            'default_environment' => 'test',
        ])->assertRedirect();

        $testConnection = IntegrationConnection::query()
            ->where('provider', 'duffel')
            ->where('environment', 'test')
            ->latest('id')
            ->firstOrFail();
        $accountKey = (string) $testConnection->account_key;

        $this->actingAs($admin)->put(route('admin.integrations.accounts.update', $accountKey), [
            'name' => 'Duffel Supplier Account Masked Updated',
            'provider' => 'duffel',
            'ownership_type' => 'platform_owner',
            'test' => [
                'base_url' => 'https://api.duffel.com',
                'client_id' => '••••••••',
                'is_active' => 1,
            ],
            'production' => ['is_active' => 0],
            'default_environment' => 'test',
        ])->assertRedirect();

        $storedToken = IntegrationCredential::query()
            ->where('integration_connection_id', $testConnection->id)
            ->where('credential_key', 'api_token')
            ->firstOrFail();
        $this->assertSame('duffel_test_supplier_masked_token', $storedToken->credential_value_encrypted);

        Http::fake([
            'https://api.duffel.com/air/airlines?limit=1' => Http::response(['data' => [['id' => 'airline_1']]], 200),
        ]);
        $this->actingAs($admin)->post(route('admin.integrations.accounts.test-connection'), [
            'connection_id' => $testConnection->id,
        ])->assertRedirect();
        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.duffel.com/air/airlines?limit=1'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_supplier_masked_token');
        });
    }
}

