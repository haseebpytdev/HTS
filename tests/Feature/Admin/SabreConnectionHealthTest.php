<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Integrations\ConnectionHealthCheckService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreConnectionHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_sabre_create_accepts_user_id_and_password_and_stores_canonical_keys(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($admin)->post(route('admin.integrations.store'), [
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'account_name' => 'Sabre Dev Hub',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => '1',
            'is_default' => '1',
            'supported_operations' => ['search'],
            'credentials' => [
                'sabre_user_id' => 'devhub-user',
                'sabre_password' => 'devhub-pass',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $connection = IntegrationConnection::query()->where('provider', 'sabre')->firstOrFail();
        $this->assertSame('sandbox', $connection->environment);
        $this->assertSame(['search'], (array) $connection->supported_operations);

        $clientId = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_id')
            ->firstOrFail();
        $clientSecret = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where('credential_key', 'client_secret')
            ->firstOrFail();

        $this->assertSame('devhub-user', $clientId->credential_value_encrypted);
        $this->assertSame('devhub-pass', $clientSecret->credential_value_encrypted);
        $this->assertNull(IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->whereIn('credential_key', ['sabre_user_id', 'sabre_password'])
            ->first());
    }

    public function test_basic_sabre_connection_test_is_token_only(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.health_checks.force_live_in_tests' => true,
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => false,
            'integrations.persist_tokens_to_database' => false,
            'sabre.auth.grant_type' => 'password',
            'sabre.token_path' => '/v2/auth/token',
            'sabre.credentials.test.client_id' => 'sabre_user',
            'sabre.credentials.test.client_secret' => 'sabre_password',
            'sabre.environments.test.base_url' => 'https://api.cert.platform.sabre.com',
            'sabre.base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'account_name' => 'Sabre Test Connection',
            'name' => 'Sabre Test Connection',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search'],
            'config' => ['sabre_deep_test' => false],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'sabre_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);
        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'token-value',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
        ]);

        $result = app(ConnectionHealthCheckService::class)->check($connection);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('token-only', $result['message']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.cert.platform.sabre.com/v2/auth/token');
    }

    public function test_optional_deep_sabre_connection_test_classifies_failure_category(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.health_checks.force_live_in_tests' => true,
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => false,
            'integrations.persist_tokens_to_database' => false,
            'sabre.auth.grant_type' => 'password',
            'sabre.token_path' => '/v2/auth/token',
            'sabre.endpoints.flight_search' => '/v5/offers/shop',
            'sabre.credentials.test.client_id' => 'sabre_user',
            'sabre.credentials.test.client_secret' => 'sabre_password',
            'sabre.environments.test.base_url' => 'https://api.cert.platform.sabre.com',
            'sabre.base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'account_name' => 'Sabre Deep Test',
            'name' => 'Sabre Deep Test',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search'],
            'config' => ['sabre_deep_test' => true],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'sabre_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);
        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'token-value',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response([
                'errors' => [['code' => 'AUTH', 'detail' => 'forbidden']],
            ], 403),
        ]);

        $result = app(ConnectionHealthCheckService::class)->check($connection);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Sabre deep test failed [entitlement]', $result['message']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.cert.platform.sabre.com/v2/auth/token');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.cert.platform.sabre.com/v5/offers/shop');
    }

    public function test_optional_deep_sabre_connection_test_classifies_payload_422_failure(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.health_checks.force_live_in_tests' => true,
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => false,
            'integrations.persist_tokens_to_database' => false,
            'sabre.auth.grant_type' => 'password',
            'sabre.token_path' => '/v2/auth/token',
            'sabre.endpoints.flight_search' => '/v5/offers/shop',
            'sabre.credentials.test.client_id' => 'sabre_user',
            'sabre.credentials.test.client_secret' => 'sabre_password',
            'sabre.environments.test.base_url' => 'https://api.cert.platform.sabre.com',
            'sabre.base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'account_name' => 'Sabre Deep Test Payload',
            'name' => 'Sabre Deep Test Payload',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search'],
            'config' => ['sabre_deep_test' => true],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'sabre_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'token-value',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response([
                'errors' => [['code' => 'INVALID_REQUEST', 'detail' => 'Bad payload']],
            ], 422),
        ]);

        $result = app(ConnectionHealthCheckService::class)->check($connection);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Sabre deep test failed [payload]', $result['message']);
    }

    public function test_optional_deep_sabre_connection_test_classifies_endpoint_404_failure(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.health_checks.force_live_in_tests' => true,
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => false,
            'integrations.persist_tokens_to_database' => false,
            'sabre.auth.grant_type' => 'password',
            'sabre.token_path' => '/v2/auth/token',
            'sabre.endpoints.flight_search' => '/v5/offers/shop',
            'sabre.credentials.test.client_id' => 'sabre_user',
            'sabre.credentials.test.client_secret' => 'sabre_password',
            'sabre.environments.test.base_url' => 'https://api.cert.platform.sabre.com',
            'sabre.base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'account_name' => 'Sabre Deep Test Endpoint',
            'name' => 'Sabre Deep Test Endpoint',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'untested',
            'supported_operations' => ['search'],
            'config' => ['sabre_deep_test' => true],
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'sabre_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'token-value',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response([
                'errors' => [['code' => 'NOT_FOUND', 'detail' => 'Endpoint not found']],
            ], 404),
        ]);

        $result = app(ConnectionHealthCheckService::class)->check($connection);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Sabre deep test failed [endpoint]', $result['message']);
    }
}
