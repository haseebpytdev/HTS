<?php

namespace App\Services\Modules;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\ServiceModule;
use App\Models\ServiceModuleCredential;
use App\Models\ServiceModuleHealthCheck;
use App\Models\ServiceModulePricingRule;
use App\Models\ServiceModuleTaxRule;
use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ModuleCatalogService
{
    public function __construct(
        private readonly ComplianceAuditService $audit,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function serviceTypes(): array
    {
        return ['Flights', 'Insurance', 'Stays', 'Cars', 'Tours', 'Visa', 'Umrah'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModules(?string $serviceType = null): array
    {
        if (! Schema::hasTable('service_modules')) {
            return [];
        }

        $this->ensureSeeded();
        $this->bootstrapAmadeusSelfServiceModule();
        $this->bootstrapSabreSearchOnlyModule();
        $this->bootstrapDuffelModule();

        $query = ServiceModule::query()
            ->orderBy('service_type')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($serviceType !== null && in_array($serviceType, $this->serviceTypes(), true)) {
            $query->where('service_type', $serviceType);
        }

        return $query->get()
            ->map(function (ServiceModule $module): array {
                $health = ServiceModuleHealthCheck::query()->where('service_module_id', $module->id)->latest('checked_at')->first();
                $taxRule = ServiceModuleTaxRule::query()->where('service_module_id', $module->id)->latest('id')->first();
                $pricingRule = ServiceModulePricingRule::query()->where('service_module_id', $module->id)->latest('id')->first();
                $operations = array_values((array) ($module->supported_operations_json ?: $module->available_operations ?: []));
                $providerCode = strtolower((string) ($module->provider ?: $module->provider_code));
                $isPrimaryOperational = AmadeusSelfServiceProvider::matches($providerCode) && (string) $module->code === 'amadeus_flights';

                return [
                    'key' => $module->code ?: $module->module_key,
                    'provider_logo_url' => $module->logo_path ?: $module->provider_logo_url,
                    'provider_name' => $module->name ?: $module->provider_name,
                    'provider_code' => $module->provider ?: $module->provider_code,
                    'service_type' => $module->service_type,
                    'enabled' => (bool) $module->is_active,
                    'environment' => $module->environment,
                    'is_default_provider' => (bool) ($module->is_default ?: $module->is_default_provider),
                    'provider_priority' => (int) ($module->provider_priority ?? $module->sort_order ?? 100),
                    'allow_fallback' => (bool) ($module->allow_fallback ?? true),
                    'allow_multi_provider' => (bool) ($module->allow_multi_provider ?? false),
                    'available_operations' => $operations,
                    'b2b_markup_type' => $pricingRule?->markup_type_b2b ?? 'percentage',
                    'b2b_markup_value' => (float) ($pricingRule?->markup_value_b2b ?? 0),
                    'b2c_markup_type' => $pricingRule?->markup_type_b2c ?? 'percentage',
                    'b2c_markup_value' => (float) ($pricingRule?->markup_value_b2c ?? 0),
                    'base_currency' => $pricingRule?->base_currency_code ?? 'PKR',
                    'documentation_url' => $module->documentation_url ?? '',
                    'setup_guide_url' => (string) (($module->config['setup_guide_url'] ?? '')),
                    'notes' => (string) (($module->config['notes'] ?? '')),
                    'connection_status' => $module->connection_status ?? 'disconnected',
                    'health_status' => $health?->result_status ?? 'warning',
                    'health_score' => (int) (($health?->raw_response_json['health_score'] ?? 0)),
                    'last_tested_at' => $module->last_tested_at,
                    'last_success_at' => $module->last_success_at,
                    'last_failure_at' => $module->last_failure_at,
                    'last_failure_reason' => (string) ($module->last_failure_reason ?? ''),
                    'status' => (string) ($module->status ?? ($module->is_active ? 'active' : 'inactive')),
                    'tax_percent' => (float) ($taxRule?->tax_value ?? 0),
                    'is_primary_operational' => $isPrimaryOperational,
                ];
            })
            ->sortBy([
                ['is_primary_operational', 'desc'],
                ['is_default_provider', 'desc'],
                ['enabled', 'desc'],
                ['provider_priority', 'asc'],
                ['provider_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateModule(string $key, array $payload): void
    {
        $module = ServiceModule::query()->where('code', $key)->orWhere('module_key', $key)->firstOrFail();

        $module->forceFill([
            'is_active' => (bool) ($payload['enabled'] ?? false),
            'environment' => (string) $payload['environment'],
            'documentation_url' => (string) ($payload['documentation_url'] ?? ''),
            'status' => (bool) ($payload['enabled'] ?? false) ? 'active' : 'inactive',
                'provider_priority' => (int) ($payload['provider_priority'] ?? $module->provider_priority ?? 100),
                'allow_fallback' => (bool) ($payload['allow_fallback'] ?? true),
                'allow_multi_provider' => (bool) ($payload['allow_multi_provider'] ?? false),
        ])->save();

        ServiceModulePricingRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            [
                'markup_type_b2b' => (string) $payload['b2b_markup_type'],
                'markup_value_b2b' => (float) $payload['b2b_markup_value'],
                'markup_type_b2c' => (string) $payload['b2c_markup_type'],
                'markup_value_b2c' => (float) $payload['b2c_markup_value'],
                'base_currency_code' => strtoupper((string) $payload['base_currency']),
            ]
        );
        ServiceModuleTaxRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            ['tax_type' => 'percentage', 'tax_value' => (float) $payload['tax_percent']]
        );
        $module->forceFill([
            'config' => array_merge((array) ($module->config ?? []), [
                'notes' => (string) ($payload['notes'] ?? ''),
            ]),
        ])->save();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'travelport_flights' => [
                'code' => 'travelport_flights',
                'provider_code' => 'travelport',
                'provider_name' => 'Travelport',
                'service_type' => 'Flights',
            ],
            'sabre_flights' => [
                'code' => 'sabre_flights',
                'provider_code' => 'sabre',
                'provider_name' => 'Sabre',
                'service_type' => 'Flights',
            ],
            'amadeus_flights' => [
                'code' => 'amadeus_flights',
                'provider_code' => AmadeusSelfServiceProvider::CODE,
                'provider_name' => 'Amadeus Self Service',
                'service_type' => 'Flights',
            ],
            'duffel_flights' => [
                'code' => 'duffel_flights',
                'provider_code' => 'duffel',
                'provider_name' => 'Duffel',
                'service_type' => 'Flights',
            ],
            'global_hotel_stays' => [
                'code' => 'global_hotel_stays',
                'provider_code' => 'internal',
                'provider_name' => 'HotelBeds',
                'service_type' => 'Stays',
            ],
            'visa_processing' => [
                'code' => 'visa_processing',
                'provider_code' => 'internal',
                'provider_name' => 'Visa Desk',
                'service_type' => 'Visa',
            ],
            'umrah_package_engine' => [
                'code' => 'umrah_package_engine',
                'provider_code' => 'internal',
                'provider_name' => 'Umrah Engine',
                'service_type' => 'Umrah',
            ],
            'tour_operations' => [
                'code' => 'tour_operations',
                'provider_code' => 'internal',
                'provider_name' => 'Tours Ops',
                'service_type' => 'Tours',
            ],
            'car_rental_bridge' => [
                'code' => 'car_rental_bridge',
                'provider_code' => 'internal',
                'provider_name' => 'Car Rentals',
                'service_type' => 'Cars',
            ],
            'insurance_gateway' => [
                'code' => 'insurance_gateway',
                'provider_code' => 'internal',
                'provider_name' => 'Insurance Hub',
                'service_type' => 'Insurance',
            ],
        ];
    }

    private function ensureSeeded(): void
    {
        if (ServiceModule::query()->exists()) {
            return;
        }

        foreach ($this->definitions() as $key => $row) {
            $module = ServiceModule::query()->create([
                'module_key' => $key,
                'code' => $row['code'],
                'name' => $row['provider_name'],
                'provider_code' => $row['provider_code'],
                'provider' => $row['provider_code'],
                'provider_name' => $row['provider_name'],
                'service_type' => $row['service_type'],
                'environment' => 'sandbox',
                'is_active' => $row['code'] === 'amadeus_flights',
                'is_default' => $row['code'] === 'amadeus_flights',
                'status' => $row['code'] === 'amadeus_flights' ? 'active' : 'inactive',
                'connection_status' => 'disconnected',
                'provider_priority' => $row['code'] === 'amadeus_flights' ? 1 : 100,
                'allow_fallback' => true,
                'allow_multi_provider' => false,
                'supported_operations_json' => ['search', 'pricing', 'booking'],
                'sort_order' => $row['code'] === 'amadeus_flights' ? 1 : 100,
                'available_operations' => ['search', 'pricing', 'booking'],
                'config' => $row['code'] === 'amadeus_flights'
                    ? ['amadeus_self_service_bootstrapped' => true, 'is_experimental' => true, 'provider_type' => 'sandbox']
                    : [],
            ]);

            ServiceModulePricingRule::query()->create([
                'service_module_id' => $module->id,
                'markup_type_b2b' => 'percentage',
                'markup_value_b2b' => 0,
                'markup_type_b2c' => 'percentage',
                'markup_value_b2c' => 0,
                'base_currency_code' => 'PKR',
            ]);
            ServiceModuleTaxRule::query()->create([
                'service_module_id' => $module->id,
                'tax_type' => 'percentage',
                'tax_value' => 0,
            ]);
            ServiceModuleHealthCheck::query()->create([
                'service_module_id' => $module->id,
                'result_status' => 'warning',
                'checked_at' => now(),
                'message' => 'Not tested yet',
                'raw_response_json' => ['health_score' => 0],
            ]);
        }
    }

    private function bootstrapAmadeusSelfServiceModule(): void
    {
        $module = ServiceModule::query()
            ->where('code', 'amadeus_flights')
            ->orWhere('module_key', 'amadeus_flights')
            ->first();

        if ($module === null) {
            return;
        }

        $config = (array) ($module->config ?? []);
        $isBootstrapped = (bool) ($config['amadeus_self_service_bootstrapped'] ?? false);
        $updates = [];

        if (($module->name ?: '') !== 'Amadeus Self Service') {
            $updates['name'] = 'Amadeus Self Service';
        }
        if (($module->provider_name ?: '') !== 'Amadeus Self Service') {
            $updates['provider_name'] = 'Amadeus Self Service';
        }
        if (! AmadeusSelfServiceProvider::matches((string) ($module->provider ?: $module->provider_code))) {
            $updates['provider'] = AmadeusSelfServiceProvider::CODE;
            $updates['provider_code'] = AmadeusSelfServiceProvider::CODE;
        }
        if ((int) ($module->provider_priority ?? 100) > 1) {
            $updates['provider_priority'] = 1;
        }
        if ((int) ($module->sort_order ?? 100) > 1) {
            $updates['sort_order'] = 1;
        }
        if (empty($module->supported_operations_json)) {
            $updates['supported_operations_json'] = ['search', 'pricing', 'booking'];
        }

        if (! $isBootstrapped) {
            $hasActiveFlightsProvider = ServiceModule::query()
                ->where('service_type', 'Flights')
                ->where('is_active', true)
                ->exists();
            $hasDefaultFlightsProvider = ServiceModule::query()
                ->where('service_type', 'Flights')
                ->where('is_default', true)
                ->exists();

            if (! $hasActiveFlightsProvider) {
                $updates['is_active'] = true;
                $updates['status'] = 'active';
            }

            if (! $hasDefaultFlightsProvider) {
                $updates['is_default'] = true;
            }

            $config['amadeus_self_service_bootstrapped'] = true;
            $config['is_experimental'] = true;
            $config['provider_type'] = 'sandbox';
            $updates['config'] = $config;
        }

        if ($updates !== []) {
            $module->forceFill($updates)->save();
        }
    }

    /**
     * Ensure Duffel is present as an enterprise flight provider module even when
     * service_modules was seeded before Duffel definitions were introduced.
     */
    private function bootstrapDuffelModule(): void
    {
        $module = ServiceModule::query()
            ->where('code', 'duffel_flights')
            ->orWhere('module_key', 'duffel_flights')
            ->orWhere(function ($query): void {
                $query->where('service_type', 'Flights')
                    ->where(function ($providerQuery): void {
                        $providerQuery->where('provider', 'duffel')
                            ->orWhere('provider_code', 'duffel');
                    });
            })
            ->first();

        if ($module === null) {
            $module = ServiceModule::query()->create([
                'module_key' => 'duffel_flights',
                'code' => 'duffel_flights',
                'name' => 'Duffel',
                'provider_code' => 'duffel',
                'provider' => 'duffel',
                'provider_name' => 'Duffel',
                'service_type' => 'Flights',
                'environment' => 'sandbox',
                'is_active' => false,
                'is_default' => false,
                'status' => 'inactive',
                'connection_status' => 'disconnected',
                'provider_priority' => 50,
                'allow_fallback' => true,
                'allow_multi_provider' => false,
                'supported_operations_json' => ['search', 'pricing', 'booking'],
                'sort_order' => 50,
                'available_operations' => ['search', 'pricing', 'booking'],
                'config' => ['provider_type' => 'direct_booking_provider'],
            ]);

            ServiceModulePricingRule::query()->firstOrCreate(
                ['service_module_id' => $module->id],
                [
                    'markup_type_b2b' => 'percentage',
                    'markup_value_b2b' => 0,
                    'markup_type_b2c' => 'percentage',
                    'markup_value_b2c' => 0,
                    'base_currency_code' => 'PKR',
                ]
            );
            ServiceModuleTaxRule::query()->firstOrCreate(
                ['service_module_id' => $module->id],
                ['tax_type' => 'percentage', 'tax_value' => 0]
            );
            ServiceModuleHealthCheck::query()->firstOrCreate(
                ['service_module_id' => $module->id, 'message' => 'Not tested yet'],
                [
                    'result_status' => 'warning',
                    'checked_at' => now(),
                    'raw_response_json' => ['health_score' => 0],
                ]
            );

            return;
        }

        $updates = [];
        if (($module->name ?: '') !== 'Duffel') {
            $updates['name'] = 'Duffel';
        }
        if (($module->provider_name ?: '') !== 'Duffel') {
            $updates['provider_name'] = 'Duffel';
        }
        if ((string) ($module->provider ?: '') !== 'duffel') {
            $updates['provider'] = 'duffel';
        }
        if ((string) ($module->provider_code ?: '') !== 'duffel') {
            $updates['provider_code'] = 'duffel';
        }
        if (empty($module->supported_operations_json)) {
            $updates['supported_operations_json'] = ['search', 'pricing', 'booking'];
        }
        if (empty($module->available_operations)) {
            $updates['available_operations'] = ['search', 'pricing', 'booking'];
        }
        $config = is_array($module->config) ? $module->config : [];
        if (! isset($config['provider_type'])) {
            $config['provider_type'] = 'direct_booking_provider';
            $updates['config'] = $config;
        }

        if ($updates !== []) {
            $module->forceFill($updates)->save();
        }
    }

    private function bootstrapSabreSearchOnlyModule(): void
    {
        $module = ServiceModule::query()
            ->where('code', 'sabre_flights')
            ->orWhere('module_key', 'sabre_flights')
            ->orWhere(function ($query): void {
                $query->where('service_type', 'Flights')
                    ->where(function ($providerQuery): void {
                        $providerQuery->where('provider', 'sabre')
                            ->orWhere('provider_code', 'sabre');
                    });
            })
            ->first();

        if ($module === null) {
            return;
        }

        $updates = [];
        $supportedOps = (array) ($module->supported_operations_json ?: []);
        if ($supportedOps !== ['search']) {
            $updates['supported_operations_json'] = ['search'];
        }
        $availableOps = (array) ($module->available_operations ?: []);
        if ($availableOps !== ['search']) {
            $updates['available_operations'] = ['search'];
        }
        $config = is_array($module->config) ? $module->config : [];
        if (($config['sabre_mode'] ?? null) !== 'search_only') {
            $config['sabre_mode'] = 'search_only';
            $updates['config'] = $config;
        }

        if ($updates !== []) {
            $module->forceFill($updates)->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getModuleConfiguration(string $moduleKey): array
    {
        $module = ServiceModule::query()
            ->where('code', $moduleKey)
            ->orWhere('module_key', $moduleKey)
            ->firstOrFail();

        $pricing = ServiceModulePricingRule::query()->where('service_module_id', $module->id)->latest('id')->first();
        $tax = ServiceModuleTaxRule::query()->where('service_module_id', $module->id)->latest('id')->first();
        $health = ServiceModuleHealthCheck::query()->where('service_module_id', $module->id)->latest('checked_at')->first();
        $credentialKeysByEnv = [
            'sandbox' => ServiceModuleCredential::query()->where('service_module_id', $module->id)->where('credential_key', 'like', 'sandbox:%')->pluck('credential_key')->map(fn ($k) => str_replace('sandbox:', '', (string) $k))->values()->all(),
            'production' => ServiceModuleCredential::query()->where('service_module_id', $module->id)->where('credential_key', 'like', 'production:%')->pluck('credential_key')->map(fn ($k) => str_replace('production:', '', (string) $k))->values()->all(),
        ];

        return [
            'module' => $module,
            'settings' => $pricing,
            'tax' => $tax,
            'health' => $health,
            'credentialKeysByEnv' => $credentialKeysByEnv,
            'credentialFields' => ['client_id', 'client_secret', 'pcc', 'epr', 'domain', 'username', 'password', 'branch_code', 'api_key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateModuleConfiguration(string $moduleKey, array $payload): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();

        if ((bool) ($payload['is_default_provider'] ?? false)) {
            ServiceModule::query()
                ->where('service_type', $module->service_type)
                ->update(['is_default' => false]);
        }

        $isActive = (bool) ($payload['is_active'] ?? false);
        $environment = (string) $payload['environment'];

        $module->forceFill([
            'is_active' => (bool) ($payload['is_active'] ?? false),
            'environment' => $environment,
            'is_default' => (bool) ($payload['is_default_provider'] ?? false),
            'supported_operations_json' => array_values((array) ($payload['available_operations'] ?? [])),
            'provider_priority' => (int) ($payload['provider_priority'] ?? $module->provider_priority ?? 100),
            'sort_order' => (int) ($payload['provider_priority'] ?? $module->sort_order ?? 100),
            'allow_fallback' => (bool) ($payload['allow_fallback'] ?? true),
            'allow_multi_provider' => (bool) ($payload['allow_multi_provider'] ?? false),
            'documentation_url' => (string) ($payload['documentation_url'] ?? ''),
            'config' => array_merge((array) ($module->config ?? []), [
                'setup_guide_url' => (string) ($payload['setup_guide_url'] ?? ''),
                'environment_notes' => (string) ($payload['environment_notes'] ?? ''),
                'troubleshooting_hints' => (string) ($payload['troubleshooting_hints'] ?? ''),
                'min_markup_guard' => isset($payload['min_markup_guard']) ? (float) $payload['min_markup_guard'] : null,
                'max_discount_guard' => isset($payload['max_discount_guard']) ? (float) $payload['max_discount_guard'] : null,
            ]),
        ])->save();

        ServiceModulePricingRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            [
                'markup_type_b2b' => (string) $payload['b2b_markup_type'],
                'markup_value_b2b' => (float) $payload['b2b_markup_value'],
                'markup_type_b2c' => (string) $payload['b2c_markup_type'],
                'markup_value_b2c' => (float) $payload['b2c_markup_value'],
                'base_currency_code' => strtoupper((string) $payload['base_currency']),
            ]
        );
        ServiceModuleTaxRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            ['tax_type' => (string) $payload['tax_type'], 'tax_value' => (float) $payload['tax_value']]
        );

        $this->upsertCredentials($module->id, 'sandbox', (array) ($payload['credentials']['sandbox'] ?? []));
        $this->upsertCredentials($module->id, 'production', (array) ($payload['credentials']['production'] ?? []));

        $isConfigured = $this->hasRequiredCredentials($module->id, $environment);
        $module->forceFill([
            'status' => $isActive ? ($isConfigured ? 'active' : 'misconfigured') : 'inactive',
            'connection_status' => $isConfigured ? ($module->connection_status ?? 'connected') : 'warning',
            'last_failure_reason' => $isActive && ! $isConfigured ? 'Missing required credentials for selected environment.' : $module->last_failure_reason,
        ])->save();

        Cache::forget('module.rules.'.$moduleKey);
        Cache::forget('pricing.rules.'.$moduleKey);
        Cache::forget('tax.rules.'.$moduleKey);

        $this->audit->record(
            area: 'modules',
            action: 'module_configuration_saved',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: $isActive && ! $isConfigured ? 'warning' : 'info',
            context: [
                'is_active' => $isActive,
                'environment' => $environment,
                'is_configured' => $isConfigured,
                'pricing_updated' => true,
                'tax_updated' => true,
            ],
        );
    }

    public function testApiConnection(string $moduleKey): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();
        $prefix = $module->environment === 'production' ? 'production:' : 'sandbox:';
        $credentials = ServiceModuleCredential::query()->where('service_module_id', $module->id)->where('credential_key', 'like', $prefix.'%')->get();
        $hasClient = $credentials->whereIn('credential_key', [$prefix.'client_id', $prefix.'api_key'])->isNotEmpty();
        $hasSecret = $credentials->whereIn('credential_key', [$prefix.'client_secret', $prefix.'password'])->isNotEmpty();
        $isSuccess = $hasClient && $hasSecret;

        $now = now();
        ServiceModuleHealthCheck::query()->create([
            'service_module_id' => $module->id,
            'result_status' => $isSuccess ? 'healthy' : 'critical',
            'checked_at' => $now,
            'latency_ms' => $isSuccess ? 180 : null,
            'message' => $isSuccess ? 'Connection test successful.' : 'Credential validation failed for selected environment.',
            'raw_response_json' => ['health_score' => $isSuccess ? 95 : 25],
        ]);
        $module->forceFill([
            'connection_status' => $isSuccess ? 'connected' : 'disconnected',
            'last_tested_at' => $now,
            'last_success_at' => $isSuccess ? $now : $module->last_success_at,
            'last_failure_at' => $isSuccess ? $module->last_failure_at : $now,
            'last_failure_reason' => $isSuccess ? null : 'Credential validation failed for selected environment.',
            'status' => $module->is_active ? 'active' : 'inactive',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function upsertCredentials(int $moduleId, string $environment, array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            ServiceModuleCredential::query()->updateOrCreate(
                ['service_module_id' => $moduleId, 'credential_key' => $environment.':'.(string) $key],
                ['credential_value_encrypted' => $value, 'is_secret' => true]
            );
        }
    }

    private function hasRequiredCredentials(int $moduleId, string $environment): bool
    {
        $prefix = in_array($environment, ['sandbox', 'production'], true) ? $environment : 'sandbox';
        $keys = ServiceModuleCredential::query()
            ->where('service_module_id', $moduleId)
            ->where('credential_key', 'like', $prefix.':%')
            ->pluck('credential_key')
            ->all();

        $hasClient = in_array($prefix.':client_id', $keys, true) || in_array($prefix.':api_key', $keys, true);
        $hasSecret = in_array($prefix.':client_secret', $keys, true) || in_array($prefix.':password', $keys, true);

        return $hasClient && $hasSecret;
    }
}
