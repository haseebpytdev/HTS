<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\IntegrationRequestLog;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class DuffelFlightSearchIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['duffel']);
    }

    public function test_duffel_flight_search_runs_offer_request_flow_and_persists_snapshots(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'production',
            'integrations.use_database_credentials' => false,
            'duffel.search.cabin_class' => 'economy',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offer_requests_path' => '/air/offer_requests',
            'duffel.credentials.production.api_token' => 'duffel_test_config_token_should_not_be_used',
        ]);

        $connection = IntegrationConnection::query()->where('provider', 'duffel')->firstOrFail();
        $connection->forceFill([
            'environment' => 'production',
            'is_active' => true,
            'status' => 'healthy',
        ])->save();
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_runtime_search_token',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.duffel.com/air/offer_requests?return_offers=true' => Http::response([
                'data' => [
                    'id' => 'orq_001',
                    'type' => 'offer_request',
                    'offers' => [[
                        'type' => 'offer',
                        'id' => 'off_001',
                        'total_currency' => 'USD',
                        'total_amount' => '245.00',
                        'base_amount' => '200.00',
                        'tax_amount' => '45.00',
                        'slices' => [[
                            'segments' => [[
                                'origin' => ['iata_code' => 'LHR'],
                                'destination' => ['iata_code' => 'JFK'],
                                'departing_at' => '2026-04-18T08:00:00Z',
                                'arriving_at' => '2026-04-18T14:00:00Z',
                                'marketing_carrier' => ['iata_code' => 'BA'],
                                'operating_carrier' => ['iata_code' => 'BA'],
                                'marketing_carrier_flight_number' => '117',
                                'cabin_class' => 'economy',
                            ]],
                        ]],
                    ]],
                ],
            ], 201),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-search-1',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'LHR',
            'destination' => 'JFK',
            'departure_date' => '2026-04-18',
            'adults' => 1,
            'provider' => 'duffel',
            'correlation_id' => 'duffel-correlation-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.driver', 'duffel');
        $response->assertJsonPath('data.correlation_id', 'duffel-correlation-1');
        $response->assertJsonPath('data.offers.0.provider_offer_reference', 'off_001');
        $response->assertJsonPath('data.offers.0.price.total_amount', 245);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.duffel.com/air/offer_requests?return_offers=true'
                && $request->hasHeader('Authorization', 'Bearer duffel_test_runtime_search_token')
                && $request->hasHeader('Duffel-Version', 'v2')
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('X-Correlation-ID')
                && data_get($data, 'data.slices.0.origin') === 'LHR'
                && data_get($data, 'data.slices.0.destination') === 'JFK'
                && data_get($data, 'data.slices.0.departure_date') === '2026-04-18'
                && data_get($data, 'data.passengers.0.type') === 'adult'
                && data_get($data, 'data.cabin_class') === 'economy'
                && data_get($data, 'data.attributes') === null;
        });

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'duffel')
                ->where('operation', 'search_offer_request')
                ->exists()
        );
        $rawRequestLog = IntegrationRequestLog::query()
            ->where('provider', 'duffel')
            ->where('operation', 'search_offer_request')
            ->latest('id')
            ->firstOrFail();
        $this->assertNotNull($rawRequestLog->responseLog);
        $this->assertSame(201, (int) $rawRequestLog->responseLog->status_code);
        $this->assertGreaterThanOrEqual(0, (int) $rawRequestLog->responseLog->latency_ms);

        $this->assertDatabaseHas('supplier_search_sessions', [
            'correlation_id' => 'duffel-correlation-1',
            'provider' => 'duffel',
            'status' => 'completed',
        ]);

        $session = SupplierSearchSession::query()->where('correlation_id', 'duffel-correlation-1')->firstOrFail();
        $this->assertSame(1, SupplierOfferSnapshot::query()->where('supplier_search_session_id', $session->id)->count());
    }

    public function test_duffel_offer_request_uses_age_for_children_instead_of_invalid_child_type(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'production',
            'integrations.use_database_credentials' => false,
            'duffel.search.cabin_class' => 'economy',
            'duffel.base_url' => 'https://api.duffel.com',
            'duffel.version' => 'v2',
            'duffel.offer_requests_path' => '/air/offer_requests',
            'duffel.credentials.production.api_token' => 'unused',
        ]);

        $connection = IntegrationConnection::query()->where('provider', 'duffel')->firstOrFail();
        $connection->forceFill([
            'environment' => 'production',
            'is_active' => true,
            'status' => 'healthy',
        ])->save();
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'api_token',
            'credential_value_encrypted' => 'duffel_test_runtime_search_token',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.duffel.com/air/offer_requests?return_offers=true' => Http::response([
                'data' => [
                    'id' => 'orq_002',
                    'type' => 'offer_request',
                    'offers' => [],
                ],
            ], 201),
        ]);

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-search-minors',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'LHR',
            'destination' => 'JFK',
            'departure_date' => '2026-05-01',
            'adults' => 1,
            'children' => 1,
            'infants' => 0,
            'provider' => 'duffel',
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return data_get($data, 'data.passengers.0.type') === 'adult'
                && data_get($data, 'data.passengers.1.age') === 10
                && data_get($data, 'data.passengers.1.type') === null;
        });
    }

    public function test_duffel_search_is_denied_when_tenant_access_is_disabled(): void
    {
        config([
            'integrations.driver' => 'duffel',
            'integrations.supported_drivers' => ['duffel'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'production',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Duffel Search Denied Tenant',
            'slug' => 'duffel-search-denied-'.uniqid(),
            'plan_tier' => 'enterprise',
        ]);
        TenantProviderAccess::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'is_enabled' => false,
            'can_search' => false,
            'can_price' => true,
            'can_book' => true,
            'allow_multi_provider' => true,
            'allow_fallback' => true,
            'priority_order' => 10,
        ]);
        IntegrationConnection::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'duffel',
            'environment' => 'production',
            'name' => 'Denied Duffel Search Connection',
            'account_name' => 'Denied Duffel Search Connection',
            'is_active' => true,
            'status' => 'healthy',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'duffel-search-tenant-denied',
        ])->postJson('/api/v1/integrations/flight-search', [
            'tenant_id' => $tenant->id,
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
            'provider' => 'duffel',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'integration_access_denied');
    }
}

