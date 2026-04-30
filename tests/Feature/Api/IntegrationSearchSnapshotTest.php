<?php

namespace Tests\Feature\Api;

use App\Models\IntegrationConnection;
use App\Models\IntegrationLog;
use App\Models\SupplierOfferSnapshot;
use App\Models\SupplierSearchSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class IntegrationSearchSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetIntegrationFixtureConfig(['travelport', 'sabre', 'amadeus']);
    }

    public function test_flight_search_writes_logs_and_snapshots_when_stub_returns_sample_offer(): void
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
            'X-Idempotency-Key' => 'snap-1',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'correlation_id' => 'test-correlation-snap-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.driver', 'travelport');
        $response->assertJsonPath('data.correlation_id', 'test-correlation-snap-1');

        $this->assertDatabaseHas('integration_logs', [
            'provider' => 'travelport',
            'log_type' => 'flight_search_completed',
        ]);

        $this->assertDatabaseHas('supplier_search_sessions', [
            'correlation_id' => 'test-correlation-snap-1',
            'status' => 'completed',
        ]);

        $session = SupplierSearchSession::query()->where('correlation_id', 'test-correlation-snap-1')->first();
        $this->assertNotNull($session);
        $this->assertSame(1, SupplierOfferSnapshot::query()->where('supplier_search_session_id', $session->id)->count());

        $this->assertGreaterThanOrEqual(2, IntegrationLog::query()->where('provider', 'travelport')->count());
    }

    public function test_flight_search_accepts_provider_override_to_travelport(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['travelport', 'sabre', 'amadeus'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'snap-2',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'provider' => 'travelport',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.driver', 'travelport');
    }

    public function test_multi_provider_search_stores_failure_and_success_logs(): void
    {
        $this->bindProviderSimulators();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['sabre', 'travelport'],
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
            'integrations.stub_provider_scenarios' => [
                'sabre' => 'timeout',
                'travelport' => 'flight_search_success',
            ],
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'snap-3',
        ])->postJson('/api/v1/integrations/flight-search', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'departure_date' => '2026-06-01',
            'multi_provider' => true,
            'providers' => ['sabre', 'travelport'],
            'provider' => 'sabre',
            'allow_fallback' => true,
            'correlation_id' => 'test-correlation-snap-3',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.failed_providers.0.provider', 'sabre')
            ->assertJsonPath('data.used_providers', fn (array $used): bool => in_array('travelport', $used, true));

        $this->assertDatabaseHas('integration_logs', [
            'provider' => 'sabre',
            'log_type' => 'flight_search_failed',
        ]);
        $this->assertDatabaseHas('integration_logs', [
            'provider' => 'travelport',
            'log_type' => 'flight_search_completed',
        ]);
    }

}
