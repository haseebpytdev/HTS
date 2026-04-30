<?php

namespace Tests\Unit\Integration;

use App\Integrations\Sabre\SabreAuthService;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Shared\SupplierTokenCache;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Repositories\IntegrationConnectionRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_and_caches_sabre_token_using_double_encoded_basic_auth(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
        ]);

        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'name' => 'Sabre Sandbox Auth',
            'account_name' => 'Sabre Sandbox Auth',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'sabre_user_id',
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
                'access_token' => 'sabre_token_123',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
        ]);

        $service = new SabreAuthService(
            new SupplierTokenCache($this->app['cache.store']),
            new IntegrationConnectionRepository,
            new IntegrationTokenRepository,
            new ProviderCredentialResolver(new IntegrationConnectionRepository)
        );

        $token = $service->getAccessToken();

        $this->assertSame('sabre_token_123', $token);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $expectedBasic = base64_encode(base64_encode('sabre_user_id').':'.base64_encode('sabre_password'));

            return $request->url() === 'https://api.cert.platform.sabre.com/v2/auth/token'
                && ($data['grant_type'] ?? null) === 'client_credentials'
                && $request->hasHeader('Authorization', 'Basic '.$expectedBasic);
        });
    }

    public function test_returns_normalized_auth_error_when_token_create_fails(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
        ]);
        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'name' => 'Sabre Sandbox Bad Credentials',
            'account_name' => 'Sabre Sandbox Bad Credentials',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'bad_user',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'bad_password',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Bad credentials',
            ], 401),
        ]);

        $service = new SabreAuthService(
            new SupplierTokenCache($this->app['cache.store']),
            new IntegrationConnectionRepository,
            new IntegrationTokenRepository,
            new ProviderCredentialResolver(new IntegrationConnectionRepository)
        );

        $this->expectException(SupplierIntegrationException::class);
        $this->expectExceptionMessage('Sabre OAuth Token Create failed with HTTP 401.');

        try {
            $service->getAccessToken();
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('supplier_auth_failed', $e->normalizedCode);
            $this->assertSame('supplier_auth_failed', $e->apiError?->code);
            $this->assertSame(401, $e->apiError?->httpStatus);
            $this->assertStringNotContainsString('bad_password', $e->getMessage());
            throw $e;
        }
    }

    public function test_returns_normalized_error_when_base_url_or_credentials_are_missing(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
        ]);

        IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'name' => 'Sabre Missing Credentials',
            'account_name' => 'Sabre Missing Credentials',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $service = new SabreAuthService(
            new SupplierTokenCache($this->app['cache.store']),
            new IntegrationConnectionRepository,
            new IntegrationTokenRepository,
            new ProviderCredentialResolver(new IntegrationConnectionRepository)
        );

        try {
            $service->getAccessToken();
            $this->fail('Expected missing Sabre credentials/base URL to throw normalized auth failure.');
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('integration_provider_unavailable', $e->normalizedCode);
            $this->assertSame('integration_provider_unavailable', $e->apiError?->code);
            $this->assertSame(503, $e->apiError?->httpStatus);
            $this->assertStringContainsString('required secrets are missing', strtolower($e->getMessage()));
        }
    }

    public function test_uses_database_stored_sabre_credentials_for_token_creation(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
            'sabre.base_url' => 'https://fallback-should-not-be-used.example',
            'sabre.credentials.test.client_id' => 'config_user',
            'sabre.credentials.test.client_secret' => 'config_password',
        ]);

        $connection = IntegrationConnection::query()->create([
            'provider' => 'sabre',
            'environment' => 'sandbox',
            'name' => 'Sabre Sandbox DB Credentials',
            'account_name' => 'Sabre Sandbox DB Credentials',
            'base_url' => 'https://api.cert.platform.sabre.com',
            'is_active' => true,
            'status' => 'healthy',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'username',
            'credential_value_encrypted' => 'db_user_id',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'password',
            'credential_value_encrypted' => 'db_password',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre_db_token_123',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
            ], 200),
        ]);

        $service = new SabreAuthService(
            new SupplierTokenCache($this->app['cache.store']),
            new IntegrationConnectionRepository,
            new IntegrationTokenRepository,
            new ProviderCredentialResolver(new IntegrationConnectionRepository)
        );

        $token = $service->getAccessToken();
        $this->assertSame('sabre_db_token_123', $token);
        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $expectedBasic = base64_encode(base64_encode('db_user_id').':'.base64_encode('db_password'));

            return $request->url() === 'https://api.cert.platform.sabre.com/v2/auth/token'
                && ($data['grant_type'] ?? null) === 'client_credentials'
                && $request->hasHeader('Authorization', 'Basic '.$expectedBasic);
        });
    }
}
