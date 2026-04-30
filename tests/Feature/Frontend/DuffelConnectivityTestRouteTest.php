<?php

namespace Tests\Feature\Frontend;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuffelConnectivityTestRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_returns_not_found_when_disabled(): void
    {
        config(['integrations.duffel_connectivity_test_enabled' => false]);
        config(['app.debug' => true]);

        $this->getJson('/test-duffel')->assertNotFound();
    }

    public function test_route_returns_not_found_when_enabled_but_not_local_and_debug_off(): void
    {
        config(['integrations.duffel_connectivity_test_enabled' => true]);
        config(['app.debug' => false]);

        $this->getJson('/test-duffel')->assertNotFound();
    }

    public function test_route_returns_json_when_enabled_and_debug_on(): void
    {
        Http::fake([
            'https://api.duffel.com/air/airlines*' => Http::response(['data' => [['id' => 'ZZ']]], 200),
        ]);

        config([
            'integrations.duffel_connectivity_test_enabled' => true,
            'integrations.enforce_database_connection_health' => false,
            'app.debug' => true,
            'integrations.credential_environment' => 'test',
            'duffel.credentials.test.api_token' => 'duffel_test_token',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
        ]);

        $this->getJson('/test-duffel')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('http_status', 200)
            ->assertJsonPath('duffel_version', 'v2');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $auth = $request->header('Authorization');

            return str_starts_with((string) $request->url(), 'https://api.duffel.com/air/airlines')
                && is_array($auth)
                && in_array('Bearer duffel_test_token', $auth, true)
                && in_array('v2', $request->header('Duffel-Version'), true);
        });
    }

    public function test_route_returns_422_when_token_missing(): void
    {
        config([
            'integrations.duffel_connectivity_test_enabled' => true,
            'integrations.enforce_database_connection_health' => false,
            'app.debug' => true,
            'integrations.credential_environment' => 'test',
            'duffel.credentials.test.api_token' => '',
            'duffel.credentials.test.client_id' => '',
            'duffel.credentials.test.client_secret' => '',
            'duffel.api_token' => '',
        ]);

        $this->getJson('/test-duffel')
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'duffel_token_missing');
    }
}
