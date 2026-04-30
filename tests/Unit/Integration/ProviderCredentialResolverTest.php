<?php

namespace Tests\Unit\Integration;

use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_config_when_database_flags_disabled(): void
    {
        config([
            'integrations.use_database_credentials' => false,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
            'travelport.credentials.test.client_id' => 'cfg-id',
            'travelport.credentials.test.client_secret' => 'cfg-secret',
            'travelport.base_url' => 'https://api.travelport.cfg',
        ]);

        $resolved = app(ProviderCredentialResolver::class)->forProvider('travelport');

        $this->assertSame('cfg-id', $resolved->clientId);
        $this->assertSame('cfg-secret', $resolved->clientSecret);
        $this->assertSame('https://api.travelport.cfg', $resolved->baseUrl);
        $this->assertNull($resolved->integrationConnectionId);
    }

    public function test_merges_database_credentials_when_enabled(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.credential_environment' => 'test',
            'travelport.credentials.test.client_id' => 'cfg-id',
            'travelport.credentials.test.client_secret' => 'cfg-secret',
            'travelport.base_url' => 'https://api.travelport.cfg',
        ]);

        $connection = IntegrationConnection::query()->create([
            'name' => 'Travelport test',
            'provider' => 'travelport',
            'environment' => 'test',
            'base_url' => 'https://oauth.from-db.test',
            'config' => null,
            'is_active' => true,
            'status' => 'healthy',
        ]);

        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'db-id',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'db-secret',
        ]);

        $resolved = app(ProviderCredentialResolver::class)->forProvider('travelport');

        $this->assertSame('db-id', $resolved->clientId);
        $this->assertSame('db-secret', $resolved->clientSecret);
        $this->assertSame('https://oauth.from-db.test', $resolved->baseUrl);
        $this->assertSame($connection->id, $resolved->integrationConnectionId);
    }

    public function test_ignores_database_row_when_not_healthy(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.enforce_database_connection_health' => false,
            'integrations.credential_environment' => 'test',
            'travelport.credentials.test.client_id' => 'cfg-id',
            'travelport.credentials.test.client_secret' => 'cfg-secret',
            'travelport.base_url' => 'https://api.travelport.cfg',
        ]);

        $connection = IntegrationConnection::query()->create([
            'name' => 'Travelport test',
            'provider' => 'travelport',
            'environment' => 'test',
            'base_url' => 'https://oauth.from-db.test',
            'config' => null,
            'is_active' => true,
            'status' => 'failed',
        ]);

        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_id',
            'credential_value_encrypted' => 'db-id',
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'client_secret',
            'credential_value_encrypted' => 'db-secret',
        ]);

        $resolved = app(ProviderCredentialResolver::class)->forProvider('travelport');

        $this->assertSame('cfg-id', $resolved->clientId);
        $this->assertSame('cfg-secret', $resolved->clientSecret);
        $this->assertNull($resolved->integrationConnectionId);
    }

    public function test_rejects_when_database_credentials_enabled_and_no_eligible_connection(): void
    {
        config([
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'integrations.enforce_database_connection_health' => true,
            'integrations.credential_environment' => 'test',
            'travelport.credentials.test.client_id' => 'cfg-id',
            'travelport.credentials.test.client_secret' => 'cfg-secret',
            'travelport.base_url' => 'https://api.travelport.cfg',
        ]);

        IntegrationConnection::query()->create([
            'name' => 'Travelport test',
            'provider' => 'travelport',
            'environment' => 'test',
            'base_url' => 'https://oauth.from-db.test',
            'config' => null,
            'is_active' => true,
            'status' => 'failed',
        ]);

        $this->expectException(\App\Integrations\Shared\Exceptions\SupplierIntegrationException::class);
        $this->expectExceptionMessage('No active healthy supplier connection is available');

        app(ProviderCredentialResolver::class)->forProvider('travelport');
    }
}
