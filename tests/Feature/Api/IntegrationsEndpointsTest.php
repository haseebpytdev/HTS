<?php

namespace Tests\Feature\Api;

use App\Models\ServiceModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class IntegrationsEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['travelport', 'sabre', 'amadeus']);
    }

    public function test_flight_search_endpoint_returns_json_envelope(): void
    {
        $this->bindProviderSimulators();
        config(['integrations.driver' => 'travelport']);
        config(['integrations.api_key' => 'test-key']);
        config(['integrations.stub_scenario' => 'flight_search_success']);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-search-1',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 2,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'driver',
                'mode',
                'correlation_id',
                'offers',
            ],
        ]);
    }

    public function test_multi_provider_flight_search_returns_comparison_payload(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport', 'sabre', 'amadeus'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-search-2',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'multi_provider' => true,
            'providers' => ['travelport', 'sabre', 'amadeus'],
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.mode', 'multi_provider');
        $response->assertJsonStructure([
            'data' => [
                'driver',
                'search_status',
                'final_provider',
                'requested_providers',
                'used_providers',
                'failed_providers',
                'comparison' => ['cheapest', 'fastest', 'best'],
                'offers',
            ],
        ]);
    }

    public function test_multi_provider_search_handles_partial_provider_failure(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport', 'sabre'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
            'integrations.stub_provider_scenarios' => [
                'sabre' => 'validation_error',
                'travelport' => 'flight_search_success',
            ],
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-search-partial',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
            'provider' => 'sabre',
            'allow_fallback' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.failed_providers.0.provider', 'sabre');
        $this->assertContains('travelport', $response->json('data.used_providers'));
    }

    public function test_multi_provider_search_falls_back_from_empty_sabre_to_travelport_offers(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
            'integrations.stub_provider_scenarios' => [
                'sabre' => 'flight_search_empty',
                'travelport' => 'flight_search_success',
            ],
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-search-empty-fallback',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'LHR',
            'destination' => 'CDG',
            'departure_date' => '2026-06-01',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
            'provider' => 'sabre',
            'allow_fallback' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.search_status', 'successful_with_offers')
            ->assertJsonPath('data.final_provider', 'travelport')
            ->assertJsonPath('data.driver', 'travelport');
        $this->assertContains('travelport', (array) $response->json('data.used_providers'));
        $this->assertNotEmpty($response->json('data.offers'));
    }

    public function test_fare_expired_returns_strict_error_envelope(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'fare_expired',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-pricing-fare-expired',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'travelport',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'fare_no_longer_available')
            ->assertJsonPath('error.supplier_code', 'TP_FARE_EXPIRED');
    }

    public function test_auth_error_returns_strict_error_envelope_without_fallback_by_default(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'auth_error',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-auth-error',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'provider' => 'sabre',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
            'allow_fallback' => true,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'supplier_auth_failed');
    }

    public function test_auth_error_can_fallback_when_explicitly_enabled(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
            'integrations.search_fallback_on_auth_or_config_errors' => true,
            'integrations.stub_provider_scenarios' => [
                'sabre' => 'auth_error',
                'travelport' => 'flight_search_success',
            ],
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-auth-error-fallback',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'provider' => 'sabre',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
            'allow_fallback' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.failed_providers.0.provider', 'sabre')
            ->assertJsonPath('data.final_provider', 'travelport')
            ->assertJsonPath('data.driver', 'travelport');
    }

    public function test_validation_failure_returns_strict_error_envelope(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'validation_error',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-validation-error',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'travelport',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'supplier_request_invalid');
    }

    public function test_pricing_revalidation_chain_falls_back_to_next_provider(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_pricing_success',
            'integrations.stub_provider_scenarios' => [
                'sabre' => 'fare_expired',
                'travelport' => 'flight_pricing_success',
            ],
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-pricing-fallback',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'sabre',
            'providers' => ['sabre', 'travelport'],
            'allow_fallback' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.driver', 'travelport')
            ->assertJsonPath('data.failed_providers.0.provider', 'sabre');
        $this->assertContains('sabre', array_column($response->json('data.failed_providers'), 'provider'));
    }

    public function test_timeout_style_failure_returns_strict_error_envelope(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'amadeus',
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'timeout',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-timeout',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'provider' => 'amadeus',
            'allow_fallback' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.failed_providers.0.provider', 'amadeus')
            ->assertJsonPath('data.used_providers', [])
            ->assertJsonPath('data.offers', []);
    }

    public function test_integrations_endpoints_require_auth_and_idempotency_headers(): void
    {
        config(['integrations.api_key' => 'test-key']);

        $this->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ])->assertStatus(401)->assertJsonPath('error.code', 'unauthorized_integration_request');

        $this->withHeaders(['X-Integration-Key' => 'test-key'])
            ->postJson('/api/v1/integrations/flight-search', [
                'origin' => 'KHI',
                'destination' => 'JED',
                'departure_date' => '2026-06-01',
                'adults' => 1,
            ])->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_idempotency_replay_returns_cached_response_header(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        $payload = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ];

        $first = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'same-key-1',
        ])->postJson('/api/v1/integrations/flight-search', $payload)->assertOk();

        $second = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'same-key-1',
        ])->postJson('/api/v1/integrations/flight-search', $payload)->assertOk();

        $this->assertSame($first->json('data.driver'), $second->json('data.driver'));
        $second->assertHeader('X-Idempotent-Replay', '1');
    }

    public function test_integrations_rate_limit_is_enforced_with_strict_error_envelope(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.api_key' => 'test-key',
            'integrations.rate_limit_per_minute' => 1,
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        $payload = [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'adults' => 1,
        ];

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'rate-1',
        ])->postJson('/api/v1/integrations/flight-search', $payload)->assertOk();

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'rate-2',
        ])->postJson('/api/v1/integrations/flight-search', $payload)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_search_filters_out_misconfigured_provider_module_in_multi_provider_mode(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport', 'sabre'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        ServiceModule::query()->create([
            'module_key' => 'travelport_flights',
            'code' => 'travelport_flights',
            'name' => 'Travelport',
            'provider' => 'travelport',
            'provider_name' => 'Travelport',
            'service_type' => 'Flights',
            'environment' => 'production',
            'is_active' => true,
            'is_default' => true,
            'status' => 'active',
            'connection_status' => 'connected',
            'supported_operations_json' => ['search', 'pricing', 'booking'],
            'sort_order' => 10,
        ]);
        ServiceModule::query()->create([
            'module_key' => 'sabre_flights',
            'code' => 'sabre_flights',
            'name' => 'Sabre',
            'provider' => 'sabre',
            'provider_name' => 'Sabre',
            'service_type' => 'Flights',
            'environment' => 'production',
            'is_active' => true,
            'is_default' => false,
            'status' => 'misconfigured',
            'connection_status' => 'warning',
            'supported_operations_json' => ['search', 'pricing', 'booking'],
            'sort_order' => 20,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-search-module-filter',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
        ]);

        $response->assertOk();
        $this->assertSame(['travelport'], $response->json('data.requested_providers'));
        $this->assertSame(['travelport'], $response->json('data.used_providers'));
    }

    public function test_pricing_returns_provider_unavailable_when_single_provider_module_is_misconfigured(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['travelport', 'sabre'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_pricing_success',
        ]);

        ServiceModule::query()->create([
            'module_key' => 'sabre_flights',
            'code' => 'sabre_flights',
            'name' => 'Sabre',
            'provider' => 'sabre',
            'provider_name' => 'Sabre',
            'service_type' => 'Flights',
            'environment' => 'production',
            'is_active' => true,
            'is_default' => true,
            'status' => 'misconfigured',
            'connection_status' => 'warning',
            'supported_operations_json' => ['search', 'pricing', 'booking'],
            'sort_order' => 10,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-pricing-module-filter',
        ])->postJson('/api/v1/integrations/flight-pricing', [
            'offer_reference' => 'TP-REF-001',
            'provider' => 'sabre',
            'allow_fallback' => false,
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }

    public function test_booking_returns_provider_unavailable_when_module_operation_is_disabled(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'booking_success',
            'integrations.booking_revalidation_required' => false,
        ]);

        ServiceModule::query()->create([
            'module_key' => 'travelport_flights',
            'code' => 'travelport_flights',
            'name' => 'Travelport',
            'provider' => 'travelport',
            'provider_name' => 'Travelport',
            'service_type' => 'Flights',
            'environment' => 'production',
            'is_active' => true,
            'is_default' => true,
            'status' => 'active',
            'connection_status' => 'connected',
            'supported_operations_json' => ['search', 'pricing'],
            'sort_order' => 10,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'idem-booking-op-disabled',
        ])->postJson('/api/v1/integrations/booking', [
            'offer_reference' => 'TP-REF-BOOK-001',
            'provider' => 'travelport',
            'travelers' => [
                [
                    'traveler_type' => 'adult',
                    'given_name' => 'Test',
                    'family_name' => 'Traveler',
                ],
            ],
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'integration_provider_unavailable');
    }

}
