<?php

namespace Tests\Feature\Integration;

use App\Integrations\Travelport\TravelportFlightSearchAdapter;
use App\Integrations\Stub\StubFlightSearchAdapter;
use App\Models\IntegrationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\InteractsWithIntegrationFixtures;
use Tests\TestCase;

class SupplierOrchestrationQualityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIntegrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(TravelportFlightSearchAdapter::class, fn () => new StubFlightSearchAdapter('travelport'));
        $this->seedAuthorizedIntegrationProviders(['travelport']);
    }

    public function test_flight_search_response_is_cached_for_short_ttl(): void
    {
        Cache::flush();
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport'],
            'integrations.search_snapshot_ttl_seconds' => 60,
            'integrations.api_key' => 'test-key',
            'integrations.stub_scenario' => 'flight_search_success',
        ]);

        $payload = [
            'origin' => 'KHI',
            'destination' => 'DXB',
            'departure_date' => now()->addDays(7)->toDateString(),
            'adults' => 1,
        ];

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'quality-1',
        ])->postJson(route('api.v1.integrations.flight-search.store'), $payload)->assertOk();
        $firstLogCount = IntegrationLog::query()->count();
        $this->assertGreaterThan(0, $firstLogCount);

        $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Idempotency-Key' => 'quality-1',
        ])->postJson(route('api.v1.integrations.flight-search.store'), $payload)->assertOk();
        $secondLogCount = IntegrationLog::query()->count();

        $this->assertSame($firstLogCount, $secondLogCount);
    }

}
