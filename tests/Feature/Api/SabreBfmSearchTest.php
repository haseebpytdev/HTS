<?php

namespace Tests\Feature\Api;

use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Sabre\SabreFlightSearchAdapter;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\IntegrationRequestLog;
use App\Models\TenantProviderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class SabreBfmSearchTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['sabre']);
    }

    public function test_sabre_bfm_logs_raw_exchange_and_returns_normalized_offer_data(): void
    {
        $this->configureSabreRestSearch(enabled: true);

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
                                    'departure' => ['airport' => 'LHR', 'time' => '2026-06-01T09:00:00'],
                                    'arrival' => ['airport' => 'JFK', 'time' => '2026-06-01T15:00:00'],
                                    'marketingCarrier' => 'BA',
                                    'operatingCarrier' => 'BA',
                                    'flightNumber' => '117',
                                    'cabin' => 'ECONOMY',
                                ]],
                            ]],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $offers = $this->adapter()->searchFlights($this->searchRequestData());
        $this->assertCount(1, $offers);
        $this->assertSame('SABRE-OFFER-1', $offers[0]->providerOfferReference);

        $requestLog = IntegrationRequestLog::query()
            ->where('provider', 'sabre')
            ->where('operation', 'search_bfm_v5')
            ->with('responseLog')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($requestLog->correlation_id);
        $this->assertSame('POST', $requestLog->http_method);
        $this->assertStringContainsString('/v5/offers/shop', $requestLog->url);
        $this->assertSame(200, (int) ($requestLog->responseLog?->status_code ?? 0));
        $this->assertGreaterThanOrEqual(0, (int) ($requestLog->responseLog?->latency_ms ?? -1));
    }

    public function test_returns_normalized_503_when_sabre_rest_search_is_disabled(): void
    {
        $this->configureSabreRestSearch(enabled: false);
        Http::fake();

        $this->expectException(SupplierIntegrationException::class);
        try {
            $this->adapter()->searchFlights($this->searchRequestData());
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('integration_provider_unavailable', $e->normalizedCode);
            $this->assertSame(503, $e->apiError?->httpStatus);
            throw $e;
        }
        Http::assertNothingSent();
    }

    public function test_maps_403_to_normalized_auth_error(): void
    {
        $this->configureSabreRestSearch(enabled: true);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $this->expectException(SupplierIntegrationException::class);
        try {
            $this->adapter()->searchFlights($this->searchRequestData());
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('supplier_auth_failed', $e->normalizedCode);
            $this->assertSame(403, $e->apiError?->httpStatus);
            throw $e;
        }
    }

    public function test_maps_400_or_422_to_supplier_request_invalid(): void
    {
        $this->configureSabreRestSearch(enabled: true);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response(['error' => 'invalid request'], 400),
        ]);

        $this->expectException(SupplierIntegrationException::class);
        try {
            $this->adapter()->searchFlights($this->searchRequestData());
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('supplier_request_invalid', $e->normalizedCode);
            $this->assertSame(400, $e->apiError?->httpStatus);
            throw $e;
        }
    }

    public function test_maps_500_to_supplier_transport_error(): void
    {
        $this->configureSabreRestSearch(enabled: true);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response(['error' => 'server error'], 500),
        ]);

        $this->expectException(SupplierIntegrationException::class);
        try {
            $this->adapter()->searchFlights($this->searchRequestData());
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('supplier_transport_error', $e->normalizedCode);
            $this->assertSame(500, $e->apiError?->httpStatus);
            throw $e;
        }
    }

    public function test_treats_http_200_without_itinerary_groups_as_successful_empty_not_failure(): void
    {
        $this->configureSabreRestSearch(enabled: true);

        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-token-1',
                'token_type' => 'Bearer',
                'expires_in' => 1800,
            ], 200),
            'https://api.cert.platform.sabre.com/v5/offers/shop' => Http::response([
                'groupedItineraryResponse' => [
                    'messages' => [[
                        'severity' => 'Error',
                        'type' => 'ERR',
                        'code' => 'ERR',
                        'text' => 'Error during Processing',
                    ]],
                    'statistics' => [
                        'itineraryCount' => 0,
                    ],
                ],
            ], 200),
        ]);

        $offers = $this->adapter()->searchFlights($this->searchRequestData());
        $this->assertSame([], $offers);

        $requestLog = IntegrationRequestLog::query()
            ->where('provider', 'sabre')
            ->where('operation', 'search_bfm_v5')
            ->with('responseLog')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(200, (int) ($requestLog->responseLog?->status_code ?? 0));
        $this->assertSame('successful_empty', $requestLog->responseLog?->error_category);
        $responseBody = is_array($requestLog->responseLog?->response_body) ? $requestLog->responseLog->response_body : [];
        $this->assertSame('successful_empty', $responseBody['_search_classification'] ?? null);
        $this->assertSame(0, $responseBody['_itinerary_count'] ?? null);
        $this->assertNotEmpty($responseBody['_sabre_messages'] ?? []);
    }

    private function configureSabreRestSearch(bool $enabled): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre'],
            'integrations.api_key' => 'test-key',
            'integrations.credential_environment' => 'test',
            'integrations.use_database_credentials' => true,
            'integrations.persist_tokens_to_database' => false,
            'sabre.live_enabled' => $enabled,
            'sabre.rest_search_enabled' => $enabled,
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
            ->update([
                'environment' => 'sandbox',
                'is_enabled' => true,
                'can_search' => true,
            ]);
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
    }

    private function searchRequestData(): FlightSearchRequestData
    {
        return new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );
    }

    private function adapter(): SabreFlightSearchAdapter
    {
        return app(SabreFlightSearchAdapter::class);
    }
}
