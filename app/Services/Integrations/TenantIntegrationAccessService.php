<?php

namespace App\Services\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\Agency;
use App\Models\Tenant;
use App\Models\TenantIntegrationPolicy;
use App\Models\TenantProviderAccess;

final class TenantIntegrationAccessService
{
    /**
     * @return array{
     *   tenant_id: int|null,
     *   provider_override: ?string,
     *   providers: list<string>|null,
     *   allow_fallback: bool,
     *   allow_multi_provider: bool
     * }
     */
    public function enforceSearchPolicy(?int $tenantId, ?int $agencyId, ?string $providerOverride, ?array $providers, bool $allowFallback, bool $allowMultiProvider): array
    {
        $effectiveTenantId = $this->resolveTenantId($tenantId, $agencyId);
        $policy = $this->effectivePolicy($effectiveTenantId);

        if (! $policy['permissions']['search']) {
            throw $this->denied('search_not_allowed', 'Search is not allowed for this tenant plan/policy.');
        }

        $allowedProviders = $policy['allowed_providers'];
        if ($allowedProviders === []) {
            throw $this->denied('provider_not_allowed', 'No providers are enabled for this tenant.');
        }

        $orderedAllowed = $this->orderedAllowedProviders($allowedProviders, $policy['provider_priority']);

        $filteredRequested = null;
        if (is_array($providers)) {
            $normalizedRequested = array_values(array_map(
                static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider),
                $providers
            ));
            $filteredRequested = array_values(array_intersect($normalizedRequested, $orderedAllowed));
            if ($filteredRequested === []) {
                throw $this->denied('provider_not_allowed', 'Requested providers are not enabled for this tenant.');
            }
        }

        $providerOverride = $providerOverride !== null && $providerOverride !== ''
            ? AmadeusSelfServiceProvider::normalize($providerOverride)
            : null;
        if ($providerOverride !== null && ! in_array($providerOverride, $orderedAllowed, true)) {
            throw $this->denied('provider_not_allowed', 'Requested provider is not enabled for this tenant.');
        }
        $effectiveOverride = ($providerOverride !== null && $providerOverride !== '') ? $providerOverride : ($orderedAllowed[0] ?? null);

        if ($allowMultiProvider && ! $policy['allow_multi_provider']) {
            throw $this->denied('multi_provider_not_allowed', 'Multi-provider search is not allowed for this tenant.');
        }
        if ($allowFallback && ! $policy['allow_fallback']) {
            throw $this->denied('fallback_not_allowed', 'Fallback search is not allowed for this tenant.');
        }

        $effectiveMulti = $allowMultiProvider && $policy['allow_multi_provider'];
        $effectiveFallback = $allowFallback && $policy['allow_fallback'];

        return [
            'tenant_id' => $effectiveTenantId,
            'provider_override' => $effectiveOverride,
            'providers' => $filteredRequested ?? $orderedAllowed,
            'allow_fallback' => $effectiveFallback,
            'allow_multi_provider' => $effectiveMulti,
        ];
    }

    public function enforceOperationProvider(?int $tenantId, ?int $agencyId, string $operation, ?string $provider): void
    {
        $provider = $provider !== null && $provider !== ''
            ? AmadeusSelfServiceProvider::normalize($provider)
            : $provider;
        $effectiveTenantId = $this->resolveTenantId($tenantId, $agencyId);
        $policy = $this->effectivePolicy($effectiveTenantId);

        if (! ($policy['permissions'][$operation] ?? false)) {
            throw $this->denied("{$operation}_not_allowed", ucfirst($operation).' is not allowed for this tenant plan/policy.');
        }

        if ($provider !== null && $provider !== '' && ! in_array($provider, $policy['allowed_providers'], true)) {
            throw $this->denied('provider_not_allowed', 'Requested provider is not enabled for this tenant.');
        }
    }

    public function resolveProviderForOperation(?int $tenantId, ?int $agencyId, string $operation, ?string $provider): string
    {
        $provider = $provider !== null && $provider !== ''
            ? AmadeusSelfServiceProvider::normalize($provider)
            : $provider;
        $this->enforceOperationProvider($tenantId, $agencyId, $operation, $provider);
        if ($provider !== null && $provider !== '') {
            return $provider;
        }

        $effectiveTenantId = $this->resolveTenantId($tenantId, $agencyId);
        $policy = $this->effectivePolicy($effectiveTenantId);
        $ordered = $this->orderedAllowedProviders($policy['allowed_providers'], $policy['provider_priority']);

        if ($ordered === []) {
            throw $this->denied('provider_not_allowed', 'No providers are enabled for this tenant.');
        }

        return $ordered[0];
    }

    /**
     * @param  list<string>|null  $providers
     * @return array{
     *   provider_override: ?string,
     *   providers: list<string>|null,
     *   allow_fallback: bool
     * }
     */
    public function enforcePricingPolicy(?int $tenantId, ?int $agencyId, ?string $providerOverride, ?array $providers, bool $allowFallback): array
    {
        $effectiveTenantId = $this->resolveTenantId($tenantId, $agencyId);
        $policy = $this->effectivePolicy($effectiveTenantId);

        if (! $policy['permissions']['pricing']) {
            throw $this->denied('pricing_not_allowed', 'Pricing is not allowed for this tenant plan/policy.');
        }

        $allowedProviders = $policy['allowed_providers'];
        if ($allowedProviders === []) {
            throw $this->denied('provider_not_allowed', 'No providers are enabled for this tenant.');
        }

        $orderedAllowed = $this->orderedAllowedProviders($allowedProviders, $policy['provider_priority']);
        $providerOverride = $providerOverride !== null && $providerOverride !== ''
            ? AmadeusSelfServiceProvider::normalize($providerOverride)
            : null;
        if ($providerOverride !== null && ! in_array($providerOverride, $orderedAllowed, true)) {
            throw $this->denied('provider_not_allowed', 'Requested provider is not enabled for this tenant.');
        }
        $effectiveOverride = ($providerOverride !== null && $providerOverride !== '') ? $providerOverride : ($orderedAllowed[0] ?? null);

        $filteredRequested = $providers !== null
            ? array_values(array_intersect(array_map(
                static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider),
                $providers
            ), $orderedAllowed))
            : null;
        if ($providers !== null && $filteredRequested === []) {
            throw $this->denied('provider_not_allowed', 'Requested providers are not enabled for this tenant.');
        }

        if ($allowFallback && ! $policy['allow_fallback']) {
            throw $this->denied('fallback_not_allowed', 'Fallback pricing is not allowed for this tenant.');
        }

        return [
            'provider_override' => $effectiveOverride,
            'providers' => $filteredRequested ?? $orderedAllowed,
            'allow_fallback' => $allowFallback && $policy['allow_fallback'],
        ];
    }

    private function resolveTenantId(?int $tenantId, ?int $agencyId): ?int
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        if ($agencyId !== null) {
            return Agency::query()->whereKey($agencyId)->value('tenant_id');
        }

        return Tenant::defaultId();
    }

    /**
     * @return array{
     *   allowed_providers: list<string>,
     *   permissions: array{search: bool, pricing: bool, booking: bool},
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   provider_priority: list<string>
     * }
     */
    private function effectivePolicy(?int $tenantId): array
    {
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        $plan = strtolower((string) ($tenant?->plan_tier ?? 'basic'));
        $base = $this->planDefaults($plan);

        if ($tenantId === null) {
            return $base;
        }

        $providerAccessRows = TenantProviderAccess::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('priority_order')
            ->get();
        if ($providerAccessRows->isNotEmpty()) {
            $enabledRows = $providerAccessRows->where('is_enabled', true);
            $allowedProviders = $enabledRows
                ->pluck('provider')
                ->map(static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider))
                ->values()
                ->all();
            $allowMulti = (bool) $enabledRows->where('allow_multi_provider', true)->count();
            $allowFallback = (bool) $enabledRows->where('allow_fallback', true)->count();
            $search = (bool) $enabledRows->where('can_search', true)->count();
            $pricing = (bool) $enabledRows->where('can_price', true)->count();
            $booking = (bool) $enabledRows->where('can_book', true)->count();
            $priority = $enabledRows
                ->sortBy('priority_order')
                ->pluck('provider')
                ->map(static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider))
                ->values()
                ->all();

            return [
                'allowed_providers' => $allowedProviders,
                'permissions' => [
                    'search' => $search,
                    'pricing' => $pricing,
                    'booking' => $booking,
                ],
                'allow_multi_provider' => $allowMulti,
                'allow_fallback' => $allowFallback,
                'provider_priority' => $priority,
            ];
        }

        $custom = TenantIntegrationPolicy::query()->where('tenant_id', $tenantId)->first();
        if ($custom === null) {
            return $base;
        }

        return [
            'allowed_providers' => array_values(array_map(
                static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider),
                $custom->allowed_providers ?? $base['allowed_providers']
            )),
            'permissions' => [
                'search' => (bool) data_get($custom->provider_permissions, 'search', $base['permissions']['search']),
                'pricing' => (bool) data_get($custom->provider_permissions, 'pricing', $base['permissions']['pricing']),
                'booking' => (bool) data_get($custom->provider_permissions, 'booking', $base['permissions']['booking']),
            ],
            'allow_multi_provider' => $custom->allow_multi_provider,
            'allow_fallback' => $custom->allow_fallback,
            'provider_priority' => array_values(array_map(
                static fn (string $provider): string => AmadeusSelfServiceProvider::normalize($provider),
                $custom->provider_priority ?? $base['provider_priority']
            )),
        ];
    }

    /**
     * @return array{
     *   allowed_providers: list<string>,
     *   permissions: array{search: bool, pricing: bool, booking: bool},
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   provider_priority: list<string>
     * }
     */
    private function planDefaults(string $plan): array
    {
        return match ($plan) {
            'growth' => [
                'allowed_providers' => [AmadeusSelfServiceProvider::CODE],
                'permissions' => ['search' => true, 'pricing' => false, 'booking' => false],
                'allow_multi_provider' => false,
                'allow_fallback' => false,
                'provider_priority' => [AmadeusSelfServiceProvider::CODE],
            ],
            'pro' => [
                'allowed_providers' => [AmadeusSelfServiceProvider::CODE, 'sabre'],
                'permissions' => ['search' => true, 'pricing' => true, 'booking' => false],
                'allow_multi_provider' => false,
                'allow_fallback' => false,
                'provider_priority' => [AmadeusSelfServiceProvider::CODE, 'sabre'],
            ],
            'enterprise' => [
                'allowed_providers' => ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'iati', 'duffel'],
                'permissions' => ['search' => true, 'pricing' => true, 'booking' => true],
                'allow_multi_provider' => true,
                'allow_fallback' => true,
                'provider_priority' => ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'iati', 'duffel'],
            ],
            default => [
                'allowed_providers' => [],
                'permissions' => ['search' => false, 'pricing' => false, 'booking' => false],
                'allow_multi_provider' => false,
                'allow_fallback' => false,
                'provider_priority' => [],
            ],
        };
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $priority
     * @return list<string>
     */
    private function orderedAllowedProviders(array $allowed, array $priority): array
    {
        $priorityAllowed = array_values(array_intersect($priority, $allowed));
        $remaining = array_values(array_diff($allowed, $priorityAllowed));

        return array_values(array_merge($priorityAllowed, $remaining));
    }

    private function denied(string $code, string $message): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'TENANT_POLICY_DENIED',
            normalizedCode: 'integration_access_denied',
            supplierContext: ['policy_code' => $code],
            apiError: new ApiErrorData(
                code: 'integration_access_denied',
                message: $message,
                supplierCode: 'TENANT_POLICY_DENIED',
                httpStatus: 403,
            ),
        );
    }
}
