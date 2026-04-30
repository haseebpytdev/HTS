<?php

namespace Tests\Unit\Integrations;

use App\Services\Integrations\ProviderResolver;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    public function test_provider_resolver_uses_configured_default_driver(): void
    {
        config([
            'integrations.driver' => 'sabre',
            'integrations.supported_drivers' => ['travelport', 'sabre', 'amadeus'],
        ]);

        $resolver = app(ProviderResolver::class);

        $this->assertSame('sabre', $resolver->driver());
        $this->assertSame('sabre', $resolver->flightSearch()->providerCode());
        $this->assertSame('sabre', $resolver->flightPricing()->providerCode());
        $this->assertSame('sabre', $resolver->booking()->providerCode());
        $this->assertSame('sabre', $resolver->auth()->providerCode());
    }
}
