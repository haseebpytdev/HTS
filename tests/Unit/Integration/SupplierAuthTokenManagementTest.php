<?php

namespace Tests\Unit\Integration;

use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\SupplierTokenCache;
use App\Integrations\Travelport\TravelportAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierAuthTokenManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_travelport_get_access_token_uses_cached_value_without_second_http_call(): void
    {
        config([
            'integrations.credential_environment' => 'test',
            'travelport.base_url' => 'https://oauth.travelport.test',
            'travelport.token_path' => '/oauth/token',
            'travelport.credentials.test.client_id' => 'cid',
            'travelport.credentials.test.client_secret' => 'sec',
        ]);

        Http::fake(function () {
            return Http::response([
                'access_token' => 'token-one',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200);
        });

        $auth = app(TravelportAuthService::class);

        $this->assertSame('token-one', $auth->getAccessToken());
        $this->assertSame('token-one', $auth->getAccessToken());

        Http::assertSentCount(1);
    }

    public function test_execute_with_auth_retry_retries_once_on_401(): void
    {
        $auth = app(TravelportAuthService::class);

        $calls = 0;
        $result = $auth->executeWithAuthRetry(function () use (&$calls, $auth) {
            $calls++;
            if ($calls === 1) {
                throw new ProviderAuthException('Unauthorized', $auth->providerCode(), 401);
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function test_supplier_token_cache_forget_clears_key(): void
    {
        $cache = app(SupplierTokenCache::class);
        $token = new \App\Data\Integrations\CachedSupplierToken(
            't',
            \Carbon\CarbonImmutable::now()->addHour(),
        );
        $cache->put('travelport', 'test', $token);
        $this->assertNotNull($cache->get('travelport', 'test'));
        $cache->forget('travelport', 'test');
        $this->assertNull($cache->get('travelport', 'test'));
    }
}
