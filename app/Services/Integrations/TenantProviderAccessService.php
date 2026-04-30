<?php

namespace App\Services\Integrations;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\ServiceModule;
use App\Models\ServiceModulePricingRule;
use App\Models\ServiceModuleTaxRule;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use Illuminate\Support\Facades\Schema;

final class TenantProviderAccessService
{
    /**
     * @return list<array{
     *   provider:string,
     *   environment:string,
     *   plan_code:?string,
     *   can_search:bool,
     *   can_price:bool,
     *   can_book:bool,
     *   allow_multi_provider:bool,
     *   allow_fallback:bool,
     *   priority_order:int,
     *   is_enabled:bool,
     *   usage_quota_json:array<string,int>,
     *   soft_limit_percent:int,
     *   hard_limit_enforced:bool,
     *   overage_alert_enabled:bool,
     *   source:string
     * }>
     */
    public function matrixForTenant(Tenant $tenant): array
    {
        $defaults = $this->planDefaults((string) ($tenant->plan_tier ?? 'basic'));
        $providerEnvironments = $this->providerEnvironmentDefaults();

        $overrides = TenantProviderAccess::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->mapWithKeys(static fn (TenantProviderAccess $row): array => [
                AmadeusSelfServiceProvider::normalize((string) $row->provider) => $row,
            ]);

        $providers = ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'iati', 'duffel'];
        $rows = [];
        foreach ($providers as $provider) {
            $base = $defaults[$provider] ?? $this->emptyDefault();
            /** @var TenantProviderAccess|null $override */
            $override = $overrides->get($provider);

            if ($override !== null) {
                $rows[] = $this->applyProviderOperationGuards($provider, [
                    'provider' => $provider,
                    'environment' => $this->normalizeEnvironment((string) ($override->environment ?? 'production')),
                    'plan_code' => $override->plan_code,
                    'can_search' => (bool) $override->can_search,
                    'can_price' => (bool) $override->can_price,
                    'can_book' => (bool) $override->can_book,
                    'allow_multi_provider' => (bool) $override->allow_multi_provider,
                    'allow_fallback' => (bool) $override->allow_fallback,
                    'priority_order' => (int) $override->priority_order,
                    'is_enabled' => (bool) $override->is_enabled,
                    'usage_quota_json' => (array) ($override->usage_quota_json ?? ['bookings_monthly' => 0, 'searches_daily' => 0]),
                    'soft_limit_percent' => (int) ($override->soft_limit_percent ?? 80),
                    'hard_limit_enforced' => (bool) ($override->hard_limit_enforced ?? false),
                    'overage_alert_enabled' => (bool) ($override->overage_alert_enabled ?? true),
                    'source' => 'override',
                ]);
                continue;
            }

            $rows[] = $this->applyProviderOperationGuards($provider, [
                'provider' => $provider,
                'environment' => (string) ($providerEnvironments[$provider] ?? 'production'),
                'plan_code' => (string) ($tenant->plan_tier ?? 'basic'),
                'can_search' => $base['can_search'],
                'can_price' => $base['can_price'],
                'can_book' => $base['can_book'],
                'allow_multi_provider' => $base['allow_multi_provider'],
                'allow_fallback' => $base['allow_fallback'],
                'priority_order' => $base['priority_order'],
                'is_enabled' => $base['is_enabled'],
                'usage_quota_json' => ['bookings_monthly' => 0, 'searches_daily' => 0],
                'soft_limit_percent' => 80,
                'hard_limit_enforced' => false,
                'overage_alert_enabled' => true,
                'source' => 'plan_default',
            ]);
        }

        usort($rows, static fn (array $a, array $b): int => $a['priority_order'] <=> $b['priority_order']);

        return array_values($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveTenantOverrides(Tenant $tenant, array $rows): void
    {
        $hasUsageQuotaJson = Schema::hasColumn('tenant_provider_access', 'usage_quota_json');
        $hasSoftLimitPercent = Schema::hasColumn('tenant_provider_access', 'soft_limit_percent');
        $hasHardLimitEnforced = Schema::hasColumn('tenant_provider_access', 'hard_limit_enforced');
        $hasOverageAlertEnabled = Schema::hasColumn('tenant_provider_access', 'overage_alert_enabled');

        foreach ($rows as $row) {
            $provider = AmadeusSelfServiceProvider::normalize((string) ($row['provider'] ?? ''));
            if (! in_array($provider, ['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'iati', 'duffel'], true)) {
                continue;
            }

            $bookingsMonthly = max(0, (int) ($row['bookings_monthly_quota'] ?? 0));
            $searchesDaily = max(0, (int) ($row['searches_daily_quota'] ?? 0));
            $softLimitPercent = min(100, max(1, (int) ($row['soft_limit_percent'] ?? 80)));
            $priorityOrder = max(1, (int) ($row['priority_order'] ?? 100));
            $updates = [
                'plan_code' => isset($row['plan_code']) ? (string) $row['plan_code'] : (string) ($tenant->plan_tier ?? 'basic'),
                'environment' => $this->normalizeEnvironment((string) ($row['environment'] ?? 'production')),
                'can_search' => (bool) ($row['can_search'] ?? false),
                'can_price' => (bool) ($row['can_price'] ?? false),
                'can_book' => (bool) ($row['can_book'] ?? false),
                'allow_multi_provider' => (bool) ($row['allow_multi_provider'] ?? false),
                'allow_fallback' => (bool) ($row['allow_fallback'] ?? false),
                'priority_order' => $priorityOrder,
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
            ];
            if ($hasUsageQuotaJson) {
                $updates['usage_quota_json'] = [
                    'bookings_monthly' => $bookingsMonthly,
                    'searches_daily' => $searchesDaily,
                ];
            }
            if ($hasSoftLimitPercent) {
                $updates['soft_limit_percent'] = $softLimitPercent;
            }
            if ($hasHardLimitEnforced) {
                $updates['hard_limit_enforced'] = (bool) ($row['hard_limit_enforced'] ?? false);
            }
            if ($hasOverageAlertEnabled) {
                $updates['overage_alert_enabled'] = (bool) ($row['overage_alert_enabled'] ?? true);
            }
            $updates = $this->applyProviderOperationGuards($provider, $updates);

            TenantProviderAccess::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'provider' => $provider,
                ],
                $updates
            );
        }
    }

    /**
     * Runtime-ready provider ordering for a tenant and operation.
     *
     * @return list<string>
     */
    public function runtimeProviderOrderForTenant(Tenant $tenant, string $operation): array
    {
        $resolution = $this->runtimeResolutionForTenant($tenant, $operation);

        return $resolution['ordered_providers'];
    }

    /**
     * Runtime provider policy for a tenant and operation sourced from DB/UI controls.
     *
     * @return array{
     *   ordered_providers: list<string>,
     *   primary_provider: ?string,
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   providers: array<string, array{
     *     provider: string,
     *     priority_order: int,
     *     operation_allowed: bool,
     *     tenant_enabled: bool,
     *     module_active: bool,
     *     module_environment: string,
     *     access_environment: string,
     *     module_health_status: string,
     *     credential_source: string,
     *     pricing: array{markup_type:string, markup_value_b2b:float, markup_value_b2c:float, base_currency:string},
     *     tax: array{tax_type:string, tax_value:float}
     *   }>
     * }
     */
    public function runtimeResolutionForTenant(Tenant $tenant, string $operation): array
    {
        $rows = $this->matrixForTenant($tenant);
        $operationKey = $this->operationKey($operation);
        if ($operationKey === null) {
            return [
                'ordered_providers' => [],
                'primary_provider' => null,
                'allow_multi_provider' => false,
                'allow_fallback' => false,
                'providers' => [],
            ];
        }

        $modulesByProvider = $this->modulesByProviderForOperation($operation);
        $orderedProviders = [];
        $providers = [];
        foreach ($rows as $row) {
            $provider = strtolower((string) ($row['provider'] ?? ''));
            if ($provider === '') {
                continue;
            }

            $module = $modulesByProvider[$provider] ?? null;
            $tenantEnabled = (bool) ($row['is_enabled'] ?? false);
            $operationAllowed = (bool) ($row[$operationKey] ?? false);
            $moduleActive = (bool) ($module['is_runtime_eligible'] ?? false);
            $accessEnvironment = $this->normalizeEnvironment((string) ($row['environment'] ?? 'production'));

            if ($module === null) {
                continue;
            }

            $providers[$provider] = [
                'provider' => $provider,
                'priority_order' => (int) ($row['priority_order'] ?? 100),
                'operation_allowed' => $operationAllowed,
                'tenant_enabled' => $tenantEnabled,
                'module_active' => $moduleActive,
                'module_environment' => $accessEnvironment,
                'access_environment' => $accessEnvironment,
                'module_health_status' => (string) ($module['health_status'] ?? 'unknown'),
                'credential_source' => (string) ($module['credential_source'] ?? 'default'),
                'pricing' => (array) ($module['pricing'] ?? [
                    'markup_type' => 'fixed',
                    'markup_value_b2b' => 0.0,
                    'markup_value_b2c' => 0.0,
                    'base_currency' => 'PKR',
                ]),
                'tax' => (array) ($module['tax'] ?? [
                    'tax_type' => 'percentage',
                    'tax_value' => 0.0,
                ]),
            ];

            if ($tenantEnabled && $operationAllowed && $moduleActive) {
                $orderedProviders[] = $provider;
            }
        }

        $orderedProviders = array_values(array_unique($orderedProviders));
        $primaryProvider = $orderedProviders[0] ?? null;
        $primaryRow = is_string($primaryProvider)
            ? collect($rows)->firstWhere('provider', $primaryProvider)
            : null;
        $primaryModule = is_string($primaryProvider) ? ($modulesByProvider[$primaryProvider] ?? null) : null;

        return [
            'ordered_providers' => $orderedProviders,
            'primary_provider' => $primaryProvider,
            'allow_multi_provider' => (bool) ($primaryRow['allow_multi_provider'] ?? false)
                && (bool) ($primaryModule['allow_multi_provider'] ?? false),
            'allow_fallback' => (bool) ($primaryRow['allow_fallback'] ?? false)
                && (bool) ($primaryModule['allow_fallback'] ?? false),
            'providers' => $providers,
        ];
    }

    /**
     * @return array<string, array{
     *   can_search: bool,
     *   can_price: bool,
     *   can_book: bool,
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   priority_order: int,
     *   is_enabled: bool
     * }>
     */
    private function planDefaults(string $plan): array
    {
        return match (strtolower($plan)) {
            'growth' => [
                AmadeusSelfServiceProvider::CODE => ['can_search' => true, 'can_price' => true, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 10, 'is_enabled' => true],
                'sabre' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 20, 'is_enabled' => false],
                'travelport' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 30, 'is_enabled' => false],
                'iati' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 40, 'is_enabled' => false],
                'duffel' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 50, 'is_enabled' => false],
            ],
            'pro' => [
                AmadeusSelfServiceProvider::CODE => ['can_search' => true, 'can_price' => true, 'can_book' => true, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 10, 'is_enabled' => true],
                'sabre' => ['can_search' => true, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 20, 'is_enabled' => false],
                'travelport' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 30, 'is_enabled' => false],
                'iati' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 40, 'is_enabled' => false],
                'duffel' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 50, 'is_enabled' => false],
            ],
            'enterprise' => [
                'travelport' => ['can_search' => true, 'can_price' => true, 'can_book' => true, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 10, 'is_enabled' => true],
                'sabre' => ['can_search' => true, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 20, 'is_enabled' => false],
                AmadeusSelfServiceProvider::CODE => ['can_search' => true, 'can_price' => true, 'can_book' => true, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 30, 'is_enabled' => true],
                'iati' => ['can_search' => true, 'can_price' => true, 'can_book' => true, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 40, 'is_enabled' => true],
                'duffel' => ['can_search' => true, 'can_price' => true, 'can_book' => true, 'allow_multi_provider' => true, 'allow_fallback' => true, 'priority_order' => 50, 'is_enabled' => true],
            ],
            default => [
                AmadeusSelfServiceProvider::CODE => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 10, 'is_enabled' => false],
                'sabre' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 20, 'is_enabled' => false],
                'travelport' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 30, 'is_enabled' => false],
                'iati' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 40, 'is_enabled' => false],
                'duffel' => ['can_search' => false, 'can_price' => false, 'can_book' => false, 'allow_multi_provider' => false, 'allow_fallback' => false, 'priority_order' => 50, 'is_enabled' => false],
            ],
        };
    }

    /**
     * @return array{
     *   can_search: bool,
     *   can_price: bool,
     *   can_book: bool,
     *   allow_multi_provider: bool,
     *   allow_fallback: bool,
     *   priority_order: int,
     *   is_enabled: bool
     * }
     */
    private function emptyDefault(): array
    {
        return [
            'can_search' => false,
            'can_price' => false,
            'can_book' => false,
            'allow_multi_provider' => false,
            'allow_fallback' => false,
            'priority_order' => 100,
            'is_enabled' => false,
        ];
    }

    private function operationKey(string $operation): ?string
    {
        return match (strtolower($operation)) {
            'search' => 'can_search',
            'pricing' => 'can_price',
            'booking' => 'can_book',
            default => null,
        };
    }

    /**
     * @return array<string, array{
     *   environment: string,
     *   health_status: string,
     *   allow_fallback: bool,
     *   allow_multi_provider: bool,
     *   credential_source: string,
     *   is_runtime_eligible: bool,
     *   pricing: array{markup_type:string, markup_value_b2b:float, markup_value_b2c:float, base_currency:string},
     *   tax: array{tax_type:string, tax_value:float}
     * }>
     */
    private function modulesByProviderForOperation(string $operation): array
    {
        if (! Schema::hasTable('service_modules')) {
            return [];
        }

        $rows = ServiceModule::query()
            ->with(['latestPricingRule', 'latestTaxRule', 'latestHealthCheck'])
            ->where('service_type', 'Flights')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('provider_priority')
            ->orderBy('sort_order')
            ->get();

        $providers = [];
        foreach ($rows as $row) {
            $provider = AmadeusSelfServiceProvider::normalize((string) ($row->provider ?: $row->provider_code));
            if ($provider === '' || isset($providers[$provider])) {
                continue;
            }

            $supportedOps = (array) ($row->supported_operations_json ?: $row->available_operations ?: []);
            if (! in_array($operation, $supportedOps, true)) {
                continue;
            }

            $config = is_array($row->config) ? $row->config : [];
            $credentialSource = strtolower((string) ($config['credential_source'] ?? 'default'));
            if (! in_array($credentialSource, ['config_only', 'database_only', 'database_preferred'], true)) {
                $credentialSource = 'default';
            }

            $latestHealth = strtolower((string) ($row->latestHealthCheck?->result_status ?? ''));
            $connectionStatus = strtolower((string) ($row->connection_status ?? 'unknown'));
            $status = strtolower((string) ($row->status ?? 'inactive'));
            $healthStatus = $latestHealth !== '' ? $latestHealth : $connectionStatus;

            /** @var ServiceModulePricingRule|null $pricingRule */
            $pricingRule = $row->latestPricingRule;
            /** @var ServiceModuleTaxRule|null $taxRule */
            $taxRule = $row->latestTaxRule;

            $providers[$provider] = [
                'environment' => $this->normalizeEnvironment((string) $row->environment),
                'health_status' => $healthStatus !== '' ? $healthStatus : 'unknown',
                'allow_fallback' => (bool) $row->allow_fallback,
                'allow_multi_provider' => (bool) $row->allow_multi_provider,
                'credential_source' => $credentialSource,
                'is_runtime_eligible' => ! in_array($status, ['inactive', 'misconfigured'], true)
                    && ! in_array($connectionStatus, ['failed', 'disconnected', 'warning'], true)
                    && ! in_array($latestHealth, ['failed'], true),
                'pricing' => [
                    'markup_type' => (string) ($pricingRule?->markup_type_b2c ?? 'fixed'),
                    'markup_value_b2b' => (float) ($pricingRule?->markup_value_b2b ?? 0),
                    'markup_value_b2c' => (float) ($pricingRule?->markup_value_b2c ?? 0),
                    'base_currency' => strtoupper((string) ($pricingRule?->base_currency_code ?? 'PKR')),
                ],
                'tax' => [
                    'tax_type' => (string) ($taxRule?->tax_type ?? 'percentage'),
                    'tax_value' => (float) ($taxRule?->tax_value ?? 0),
                ],
            ];
        }

        return $providers;
    }

    /**
     * @return array<string, string>
     */
    private function providerEnvironmentDefaults(): array
    {
        if (! Schema::hasTable('service_modules')) {
            return [];
        }

        $rows = ServiceModule::query()
            ->where('service_type', 'Flights')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('provider_priority')
            ->orderBy('sort_order')
            ->get();

        $defaults = [];
        foreach ($rows as $row) {
            $provider = AmadeusSelfServiceProvider::normalize((string) ($row->provider ?: $row->provider_code));
            if ($provider === '' || isset($defaults[$provider])) {
                continue;
            }

            $defaults[$provider] = $this->normalizeEnvironment((string) $row->environment);
        }

        return $defaults;
    }

    private function normalizeEnvironment(string $environment): string
    {
        $normalized = strtolower(trim($environment));

        return match ($normalized) {
            'sandbox', 'test', 'testing', 'development' => 'sandbox',
            default => 'production',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyProviderOperationGuards(string $provider, array $row): array
    {
        if (strtolower($provider) === 'sabre') {
            $row['can_price'] = false;
            $row['can_book'] = false;
        }

        return $row;
    }
}
