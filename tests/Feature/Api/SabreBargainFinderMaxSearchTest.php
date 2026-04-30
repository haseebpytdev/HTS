<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\IntegrationRequestLog;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class SabreBargainFinderMaxSearchTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['sabre']);
    }

    public function test_sabre_bfm_search_returns_normalized_offers_and_records_snapshots(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'sabre.live_enabled' => true,
            'sabre.rest_search_enabled' => true,
            'sabre.soap_enabled' => false,
            'sabre.auth.grant_type' => 'password',
            'sabre.token_path' => '/v2/auth/token',
            'sabre.endpoints.flight_search' => '/v5/offers/shop',
            'sabre.base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $connection = IntegrationConnection::query()->where('provider', 'sabre')->firstOrFail();
        $connection->forceFill([
            'environment' => 'sandbox',
            'is_active' => true,
            'status' => 'healthy',
            'base_url' => 'https://api.cert.platform.sabre.com',
        ])->save();
        TenantProviderAccess::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('provider', 'sabre')
            ->update(['environment' => 'sandbox']);

        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'username',
            'credential_value_encrypted' => 'sabre_user_id',
            'is_secret' => false,
        ]);
        IntegrationCredential::query()->create([
            'integration_connection_id' => $connection->id,
            'credential_key' => 'password',
            'credential_value_encrypted' => 'sabre_password',
            'is_secret' => true,
        ]);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response([
                'groupedItineraryResponse' => [
                    'itineraryGroups' => [[
                        'itineraries' => [[
                            'id' => 'itn_1',
                            'pricingToken' => 'SABRE-OFFER-1',
                            'pricingInformation' => [[
                                'fare' => [
                                    'currency' => 'USD',
                                    'baseFare' => 100,
                                    'taxes' => 20,
                                    'totalFare' => 120,
                                ],
                            ]],
                            'legs' => [[
                                'segments' => [[
                                    'departure' => ['airport' => 'KHI', 'time' => '2026-06-20T09:00:00'],
                                    'arrival' => ['airport' => 'JED', 'time' => '2026-06-20T12:00:00'],
                                    'marketingCarrier' => 'SV',
                                    'operatingCarrier' => 'SV',
                                    'flightNumber' => '701',
                                    'cabin' => 'ECONOMY',
                                ]],
                            ]],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'sabre-bfm-1',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-20',
            'adults' => 1,
            'provider' => 'sabre',
            'correlation_id' => 'sabre-bfm-correlation-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.offers.0.provider_offer_reference', 'SABRE-OFFER-1');
        $response->assertJsonPath('data.offers.0.price.total_amount', 120);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.cert.platform.sabre.com/v5/offers/shop') {
                return false;
            }

            $data = $request->data();

            return $request->hasHeader('Authorization', 'Bearer sabre-token-1')
                && $request->hasHeader('X-Correlation-ID')
                && data_get($data, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.OriginLocation.LocationCode') === 'KHI'
                && data_get($data, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.DestinationLocation.LocationCode') === 'JED'
                && data_get($data, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity.0.Code') === 'ADT';
        });

        $this->assertTrue(
            IntegrationRequestLog::query()
                ->where('provider', 'sabre')
                ->where('operation', 'search_bfm_v5')
                ->exists()
        );
        $this->assertDatabaseHas('supplier_search_sessions', [
            'correlation_id' => 'sabre-bfm-correlation-1',
            'provider' => 'sabre',
            'status' => 'completed',
        ]);
        $session = SupplierSearchSession::query()->where('correlation_id', 'sabre-bfm-correlation-1')->firstOrFail();
        $this->assertSame(1, SupplierOfferSnapshot::query()->where('supplier_search_session_id', $session->id)->count());
    }
}
