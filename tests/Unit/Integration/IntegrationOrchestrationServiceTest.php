<?php

namespace Tests\Unit\Integration;

use App\Models\IntegrationLog;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\IntegrationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class IntegrationOrchestrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_driver_uses_default_from_config(): void
    {
        config(['integrations.driver' => 'sabre', 'integrations.supported_drivers' => ['stub', 'sabre']]);

        $svc = new IntegrationOrchestrationService(app(IntegrationProviderRegistry::class));

        $this->assertSame('sabre', $svc->resolveDriver(null));
    }

    public function test_resolve_driver_accepts_override(): void
    {
        config(['integrations.driver' => 'stub', 'integrations.supported_drivers' => ['stub', 'travelport']]);

        $svc = new IntegrationOrchestrationService(app(IntegrationProviderRegistry::class));

        $this->assertSame('travelport', $svc->resolveDriver('travelport'));
    }

    public function test_resolve_driver_rejects_unknown_override(): void
    {
        config(['integrations.supported_drivers' => ['stub']]);

        $svc = new IntegrationOrchestrationService(app(IntegrationProviderRegistry::class));

        $this->expectException(InvalidArgumentException::class);
        $svc->resolveDriver('travelport');
    }

    public function test_resolve_provider_order_prioritizes_override_and_deduplicates(): void
    {
        config([
            'integrations.driver' => 'stub',
            'integrations.supported_drivers' => ['stub', 'travelport', 'sabre'],
        ]);

        $svc = new IntegrationOrchestrationService(app(IntegrationProviderRegistry::class));

        $order = $svc->resolveProviderOrder('travelport', ['sabre', 'travelport']);

        $this->assertSame(['travelport', 'sabre'], $order);
    }

    public function test_resolve_provider_order_uses_health_scoring_for_dynamic_ordering(): void
    {
        config([
            'integrations.driver' => 'stub',
            'integrations.supported_drivers' => ['stub', 'travelport', 'sabre'],
        ]);

        IntegrationLog::query()->create([
            'provider' => 'travelport',
            'correlation_id' => 'c1',
            'log_type' => 'flight_search_failed',
            'payload' => ['latency_ms' => 1200],
            'created_at' => now(),
        ]);
        IntegrationLog::query()->create([
            'provider' => 'sabre',
            'correlation_id' => 'c2',
            'log_type' => 'flight_search_completed',
            'payload' => ['latency_ms' => 200],
            'created_at' => now(),
        ]);

        $svc = app(IntegrationOrchestrationService::class);
        $order = $svc->resolveProviderOrder(null, ['travelport', 'sabre']);

        $this->assertSame(['sabre', 'travelport'], $order);
    }
}
