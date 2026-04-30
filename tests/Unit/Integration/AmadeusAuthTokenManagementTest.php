<?php

namespace Tests\Unit\Integration;

use App\Integrations\Amadeus\AmadeusAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmadeusAuthTokenManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_amadeus_get_access_token_uses_cached_value_without_second_http_call(): void
    {
        config([
            'integrations.credential_environment' => 'test',
            'amadeus.base_url' => 'https://test.api.amadeus.com',
            'amadeus.token_path' => '/v1/security/oauth2/token',
            'amadeus.credentials.test.client_id' => 'cid',
            'amadeus.credentials.test.client_secret' => 'sec',
        ]);

        Http::fake(function () {
            return Http::response([
                'access_token' => 'amadeus-token-one',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200);
        });

        $auth = app(AmadeusAuthService::class);
        $this->assertSame('amadeus-token-one', $auth->getAccessToken());
        $this->assertSame('amadeus-token-one', $auth->getAccessToken());

        Http::assertSentCount(1);
    }
}

