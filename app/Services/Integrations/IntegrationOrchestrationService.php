<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\Agency;
use App\Models\ServiceModule;
use App\Models\Tenant;
use App\Services\Modules\ModuleRuntimeConfigService;
use App\Services\System\SystemSettingsService;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class IntegrationOrchestrationService
{
    public function __construct(
        private readonly IntegrationProviderRegistry $registry,
        private readonly ?ProviderHealthScoringService $healthScoring = null,
        private readonly ?TenantProviderAuthorizationService $tenantProviderAuthorization = null,
        private readonly ?TenantProviderAccessService $tenantProviderAccess = null,
        private readonly ?ModuleRuntimeConfigService $moduleRuntimeConfig = null,
        private readonly ?SystemSettingsService $settings = null,
    ) {
    }

    /**
     * @param  list<string>|null  $supported
     */
    public function resolveDriver(
        ?string $override,
        ?array $supported = null,
        ?string $operation = null,
        ?int $tenantId = null,
        ?int $agencyId = null
    ): string
    {
        /** @var list<string> $supportedList */
        $supportedList = $supported ?? $this->supportedDrivers($operation);
        $default = $this->defaultDriver($supportedList, $operation, $tenantId, $agencyId);

        $normalizedOverride = $override !== null && $override !== ''
            ? AmadeusSelfServiceProvider::normalize($override)
            : null;

        if ($normalizedOverride === null) {
            if (! in_array($default, $supportedList, true)) {
                return 'stub';
            }

            return $default;
        }

        if (! in_array($normalizedOverride, $supportedList, true)) {
            throw new InvalidArgumentException('Unsupported integration driver: '.$normalizedOverride);
        }

        return $normalizedOverride;
    }

    /**
     * @param  list<string>  $supportedList
     */
    private function defaultDriver(array $supportedList, ?string $operation = null, ?int $tenantId = null, ?int $agencyId = null): string
    {
        $fallback = AmadeusSelfServiceProvider::normalize((string) config('integrations.driver', 'stub'));
        if ($operation !== null) {
            $resolution = $this->runtimeResolutionForOperation($tenantId, $agencyId, $operation);
            $runtimeDefault = strtolower((string) ($resolution['primary_provider'] ?? ''));
            if ($runtimeDefault !== '' && in_array($runtimeDefault, $supportedList, true)) {
                return $runtimeDefault;
            }
        }

        $modulePreferred = null;
        if (Schema::hasTable('service_modules')) {
            $modulePreferred = ServiceModule::query()
                ->where('service_type', 'Flights')
                ->where('is_active', true)
                ->whereIn('status', ['active', 'healthy'])
                ->whereIn('connection_status', ['connected', 'healthy'])
                ->orderByDesc('is_default')
                ->orderBy('provider_priority')
                ->orderBy('sort_order')
                ->first();
        }
        $moduleProvider = AmadeusSelfServiceProvider::normalize((string) ($modulePreferred?->provider ?: $modulePreferred?->provider_code ?: ''));
        if ($moduleProvider !== '' && in_array($moduleProvider, $supportedList, true)) {
            return $moduleProvider;
        }

        $fromDb = AmadeusSelfServiceProvider::normalize((string) ($this->settings?->getString(
            key: 'integrations.default_provider',
            default: $fallback,
            context: [
                'scope' => 'platform',
                'category' => 'integrations',
            ],
            configFallbackKey: 'integrations.driver'
        ) ?? ''));
        if ($fromDb !== '' && in_array($fromDb, $supportedList, true)) {
            return $fromDb;
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    public function supportedDrivers(?string $operation = null): array
    {
        /** @var list<string> $supported */
        $supported = array_values(array_unique(array_map(
            static fn (string $driver): string => AmadeusSelfServiceProvider::normalize($driver),
            config('integrations.supported_drivers', ['stub', 'travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'duffel'])
        )));

        if ($operation !== null && $this->moduleRuntimeConfig !== null) {
            $active = $this->moduleRuntimeConfig->activeProvidersForOperation($operation);
            if ($active !== []) {
                $supported = array_values(array_intersect($supported, $active));
            }
        }

        return $supported;
    }

    /**
     * Resolve provider order for multi-provider search/fallback.
     *
     * @param  list<string>|null  $requestedProviders
     * @return list<string>
     */
    public function resolveProviderOrder(
        ?string $primaryOverride = null,
        ?array $requestedProviders = null,
        ?string $operation = null,
        ?int $tenantId = null,
        ?int $agencyId = null
    ): array
    {
        $supported = $this->supportedDrivers($operation);
        $requestedOrder = [];
        $override = null;
        $runtimeResolution = $operation !== null
            ? $this->runtimeResolutionForOperation($tenantId, $agencyId, $operation)
            : null;

        if ($primaryOverride !== null && $primaryOverride !== '') {
            $override = $this->resolveDriver($primaryOverride, $supported);
            $requestedOrder[] = $override;
        }

        foreach (($requestedProviders ?? []) as $driver) {
            $driverString = AmadeusSelfServiceProvider::normalize((string) $driver);
            if (! in_array($driverString, $supported, true)) {
                continue;
            }
            $resolved = $this->resolveDriver($driverString, $supported);
            if (! in_array($resolved, $requestedOrder, true)) {
                $requestedOrder[] = $resolved;
            }
        }

        if ($requestedOrder === []) {
            $runtimeOrdered = is_array($runtimeResolution)
                ? array_values(array_intersect($runtimeResolution['ordered_providers'] ?? [], $supported))
                : [];
            $requestedOrder = $runtimeOrdered !== [] ? $runtimeOrdered : $supported;
            $default = $this->resolveDriver(null, $supported, $operation, $tenantId, $agencyId);
            $requestedOrder = array_values(array_unique(array_merge([$default], $requestedOrder)));
        }

        $reorderable = $requestedOrder;
        if ($override !== null) {
            $reorderable = array_values(array_filter($requestedOrder, static fn (string $d): bool => $d !== $override));
        }

        if ($this->healthScoring !== null && $reorderable !== []) {
            $reorderable = $this->healthScoring->rankDrivers($reorderable);
        }

        if ($override !== null) {
            return array_values(array_merge([$override], $reorderable));
        }

        return $reorderable;
    }

    /**
     * @param  list<string>|null  $requestedProviders
     * @return list<string>
     */
    public function resolveAuthorizedProviderOrder(
        ?int $tenantId,
        ?int $agencyId,
        string $operation,
        ?string $primaryOverride = null,
        ?array $requestedProviders = null,
        bool $respectRuntimeSingleProvider = true
    ): array {
        $resolution = $this->runtimeResolutionForOperation($tenantId, $agencyId, $operation);
        $order = $this->resolveProviderOrder($primaryOverride, $requestedProviders, $operation, $tenantId, $agencyId);
        $tenant = $this->resolveTenant($tenantId, $agencyId);
        $dbOrder = array_values(array_unique(array_map(
            static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider),
            (array) ($resolution['ordered_providers'] ?? [])
        )));
        if ($dbOrder !== []) {
            $order = array_values(array_unique(array_merge($dbOrder, $order)));
        }
        if ($this->tenantProviderAuthorization === null) {
            return $order;
        }

        $authorized = [];
        $lastException = null;
        foreach ($order as $provider) {
            try {
                $this->tenantProviderAuthorization->authorizeProvider(
                    tenantId: $tenantId,
                    agencyId: $agencyId,
                    provider: $provider,
                    operation: $operation,
                );
                $authorized[] = $provider;
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if ($authorized === [] && $lastException instanceof \Throwable) {
            throw $lastException;
        }

        if ($respectRuntimeSingleProvider && $tenant !== null && $authorized !== [] && ($resolution['primary_provider'] ?? null) !== null) {
            $allowMulti = (bool) ($resolution['allow_multi_provider'] ?? false);
            $allowFallback = (bool) ($resolution['allow_fallback'] ?? false);
            if (! $allowMulti || ! $allowFallback) {
                return [(string) $authorized[0]];
            }
        }

        return $authorized;
    }

    /**
     * @return array{
     *   ordered_providers:list<string>,
     *   primary_provider:?string,
     *   allow_multi_provider:bool,
     *   allow_fallback:bool,
     *   providers:array<string, mixed>
     * }
     */
    public function runtimeResolutionForOperation(?int $tenantId, ?int $agencyId, string $operation): array
    {
        $tenant = $this->resolveTenant($tenantId, $agencyId);
        if ($tenant === null || $this->tenantProviderAccess === null) {
            return [
                'ordered_providers' => [],
                'primary_provider' => null,
                'allow_multi_provider' => false,
                'allow_fallback' => false,
                'providers' => [],
            ];
        }

        return $this->tenantProviderAccess->runtimeResolutionForTenant($tenant, $operation);
    }

    private function resolveTenant(?int $tenantId, ?int $agencyId): ?Tenant
    {
        if ($tenantId !== null) {
            return Tenant::query()->find($tenantId);
        }
        if ($agencyId !== null) {
            $resolvedTenantId = Agency::query()->whereKey($agencyId)->value('tenant_id');
            if ($resolvedTenantId !== null) {
                return Tenant::query()->find((int) $resolvedTenantId);
            }
        }

        $defaultTenantId = Tenant::defaultId();

        return $defaultTenantId !== null ? Tenant::query()->find($defaultTenantId) : null;
    }

    public function flightSearch(string $driver): FlightSearchProviderInterface
    {
        return $this->registry->flightSearch($driver);
    }

    public function flightPricing(string $driver): FlightPricingProviderInterface
    {
        return $this->registry->flightPricing($driver);
    }

    public function booking(string $driver): BookingProviderInterface
    {
        return $this->registry->booking($driver);
    }

    public function auth(string $driver): AuthTokenProviderInterface
    {
        return $this->registry->auth($driver);
    }
}
