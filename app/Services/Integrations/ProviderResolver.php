<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Contracts\Integrations\FlightSearchProviderInterface;

/**
 * Resolves the default-configured integration implementations (same driver for all surfaces).
 */
final class ProviderResolver
{
    public function __construct(
        private readonly IntegrationOrchestrationService $orchestration,
    ) {
    }

    public function driver(): string
    {
        return $this->orchestration->resolveDriver(null, null, 'search');
    }

    public function driverForOperation(?int $tenantId, ?int $agencyId, string $operation, ?string $override = null): string
    {
        $runtime = $this->runtimeSelectionForOperation($tenantId, $agencyId, $operation, $override);

        return $runtime['driver'] ?? $this->driver();
    }

    /**
     * @return array{
     *   driver: ?string,
     *   ordered_providers: list<string>,
     *   primary_provider: ?string,
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   providers: array<string, mixed>
     * }
     */
    public function runtimeSelectionForOperation(?int $tenantId, ?int $agencyId, string $operation, ?string $override = null): array
    {
        $resolution = $this->orchestration->runtimeResolutionForOperation($tenantId, $agencyId, $operation);
        $order = $this->orchestration->resolveAuthorizedProviderOrder(
            tenantId: $tenantId,
            agencyId: $agencyId,
            operation: $operation,
            primaryOverride: $override,
            requestedProviders: null,
        );

        return [
            'driver' => $order[0] ?? null,
            'ordered_providers' => $order,
            'primary_provider' => $resolution['primary_provider'] ?? null,
            'allow_multi_provider' => (bool) ($resolution['allow_multi_provider'] ?? false),
            'allow_fallback' => (bool) ($resolution['allow_fallback'] ?? false),
            'providers' => (array) ($resolution['providers'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    public function driverOrderForOperation(?int $tenantId, ?int $agencyId, string $operation, ?string $override = null): array
    {
        return $this->orchestration->resolveAuthorizedProviderOrder(
            tenantId: $tenantId,
            agencyId: $agencyId,
            operation: $operation,
            primaryOverride: $override,
            requestedProviders: null,
        );
    }

    public function flightSearch(): FlightSearchProviderInterface
    {
        return $this->orchestration->flightSearch($this->driver());
    }

    public function flightPricing(): FlightPricingProviderInterface
    {
        return $this->orchestration->flightPricing($this->driver());
    }

    public function booking(): BookingProviderInterface
    {
        return $this->orchestration->booking($this->driver());
    }

    public function auth(): AuthTokenProviderInterface
    {
        return $this->orchestration->auth($this->driver());
    }
}
