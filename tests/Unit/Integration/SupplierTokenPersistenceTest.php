<?php

namespace Tests\Unit\Integration;

use App\Integrations\Travelport\TravelportAuthService;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\IntegrationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierTokenPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_persists_access_token_when_database_persistence_enabled(): void
    {
        config([
            'integrations.persist_tokens_to_database' => true,
            'integrations.use_database_credentials' => true,
            'integrations.credential_environment' => 'test',
            'travelport.base_url' => 'https://oauth.travelport.test',
            'travelport.token_path' => '/oauth/token',
        ]);

        $connection = IntegrationConnection::query()->create([
            'name' => 'Travelport test',
            'provider' => 'travelport',
            'environment' => 'test',
            'base_url' => null,
            'config' => null,
            'is_active' => true,
            'status' => 'healthy',
        ]);

        foreach (['client_id' => 'cid', 'client_secret' => 'sec'] as $key => $value) {
            IntegrationCredential::query()->create([
                'integration_connection_id' => $connection->id,
                'credential_key' => $key,
                'credential_value_encrypted' => $value,
                'is_secret' => true,
            ]);
        }

        Http::fake([
            'https://oauth.travelport.test/oauth/token' => Http::response([
                'access_token' => 'persisted-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $auth = app(TravelportAuthService::class);
        $this->assertSame('persisted-token', $auth->getAccessToken());

        $this->assertSame(1, IntegrationToken::query()->where('integration_connection_id', $connection->id)->count());
        $this->assertSame('persisted-token', IntegrationToken::query()->first()->token);
    }
}
