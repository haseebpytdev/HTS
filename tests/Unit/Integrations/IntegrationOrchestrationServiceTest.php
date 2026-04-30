<?php

namespace Tests\Unit\Integrations;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Services\Integrations\IntegrationOrchestrationService;
use App\Services\Integrations\IntegrationProviderRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class IntegrationOrchestrationServiceTest extends TestCase
{
    public function test_resolve_driver_uses_default_and_accepts_override(): void
    {
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE],
        ]);

        $svc = app(IntegrationOrchestrationService::class);

        $this->assertSame('travelport', $svc->resolveDriver(null));
        $this->assertSame('sabre', $svc->resolveDriver('sabre'));
    }

    public function test_resolve_driver_rejects_unknown_override(): void
    {
        config(['integrations.supported_drivers' => ['travelport', 'sabre']]);
        $svc = app(IntegrationOrchestrationService::class);

        $this->expectException(InvalidArgumentException::class);
        $svc->resolveDriver('unknown-provider');
    }

    public function test_provider_order_supports_override_and_deduping(): void
    {
        config([
            'integrations.driver' => 'travelport',
            'integrations.supported_drivers' => ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE],
        ]);

        $svc = new IntegrationOrchestrationService(app(IntegrationProviderRegistry::class));

        $ordered = $svc->resolveProviderOrder('travelport', ['sabre', AmadeusSelfServiceProvider::LEGACY_CODE, 'travelport']);

        $this->assertSame('travelport', $ordered[0]);
        $this->assertContains('sabre', $ordered);
        $this->assertContains(AmadeusSelfServiceProvider::CODE, $ordered);
        $this->assertSame(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE], $ordered);
    }
}
