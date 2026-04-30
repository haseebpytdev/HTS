<?php

namespace App\Services\Modules;

use App\Models\ServiceModule;
use App\Models\ServiceModulePricingRule;
use App\Models\ServiceModuleTaxRule;
use App\Services\System\SystemSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ModuleRuntimeConfigService
{
    public function __construct(
        private readonly SystemSettingsService $settingsService,
    ) {
    }

    /**
     * @return array{markup_type: string, markup_value: float, base_currency: string}
     */
    public function pricingDefaultsForServiceType(string $serviceType, string $audience = 'b2c'): array
    {
        $cacheKey = 'module.rules.'.strtolower($serviceType).'.pricing.'.$audience;

        /** @var array{markup_type: string, markup_value: float, base_currency: string} $resolved */
        $resolved = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($serviceType, $audience): array {
            $module = $this->activeModuleForServiceType($serviceType);
            if ($module === null) {
                $defaultMarkupMode = $this->settingsService->getString(
                    key: 'pricing.markup_mode',
                    default: 'fixed',
                    context: ['scope' => 'platform', 'category' => 'pricing']
                ) ?? 'fixed';
                $defaultMarkupValue = $this->settingsService->getFloat(
                    key: 'pricing.markup_value',
                    default: 0.0,
                    context: ['scope' => 'platform', 'category' => 'pricing']
                );
                $defaultCurrency = strtoupper((string) ($this->settingsService->getString(
                    key: 'app.localization.default_currency',
                    default: 'PKR',
                    context: ['scope' => 'platform', 'category' => 'localization'],
                    configFallbackKey: 'app.currency'
                ) ?? 'PKR'));

                return [
                    'markup_type' => in_array($defaultMarkupMode, ['fixed', 'percentage'], true) ? $defaultMarkupMode : 'fixed',
                    'markup_value' => max($defaultMarkupValue, 0),
                    'base_currency' => $defaultCurrency !== '' ? $defaultCurrency : 'PKR',
                ];
            }

            $rule = ServiceModulePricingRule::query()
                ->where('service_module_id', $module->id)
                ->latest('id')
                ->first();

            $isB2b = strtolower($audience) === 'b2b';

            return [
                'markup_type' => (string) ($isB2b ? ($rule?->markup_type_b2b ?? 'fixed') : ($rule?->markup_type_b2c ?? 'fixed')),
                'markup_value' => (float) ($isB2b ? ($rule?->markup_value_b2b ?? 0) : ($rule?->markup_value_b2c ?? 0)),
                'base_currency' => strtoupper((string) ($rule?->base_currency_code ?? 'PKR')),
            ];
        });

        return $resolved;
    }

    /**
     * @return array{tax_type: string, tax_value: float}
     */
    public function taxDefaultsForServiceType(string $serviceType): array
    {
        $cacheKey = 'module.rules.'.strtolower($serviceType).'.tax';

        /** @var array{tax_type: string, tax_value: float} $resolved */
        $resolved = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($serviceType): array {
            $module = $this->activeModuleForServiceType($serviceType);
            if ($module === null) {
                $defaultTaxPercent = $this->settingsService->getFloat(
                    key: 'tax.default_percent',
                    default: 0.0,
                    context: ['scope' => 'platform', 'category' => 'tax']
                );

                return ['tax_type' => 'percentage', 'tax_value' => max($defaultTaxPercent, 0)];
            }

            $rule = ServiceModuleTaxRule::query()
                ->where('service_module_id', $module->id)
                ->latest('id')
                ->first();

            return [
                'tax_type' => (string) ($rule?->tax_type ?? 'percentage'),
                'tax_value' => (float) ($rule?->tax_value ?? 0),
            ];
        });

        return $resolved;
    }

    public function calculateTaxAmount(string $serviceType, float $taxableAmount): float
    {
        $tax = $this->taxDefaultsForServiceType($serviceType);
        if ($taxableAmount <= 0) {
            return 0.0;
        }

        if ($tax['tax_type'] === 'fixed') {
            return round(max($tax['tax_value'], 0), 2);
        }

        return round(($taxableAmount * max($tax['tax_value'], 0)) / 100, 2);
    }

    /**
     * @return list<string>
     */
    public function activeProvidersForOperation(string $operation): array
    {
        if (! Schema::hasTable('service_modules')) {
            return [];
        }

        $rows = ServiceModule::query()
            ->where('service_type', 'Flights')
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('connection_status', 'connected')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        $providers = [];
        foreach ($rows as $row) {
            $ops = (array) ($row->supported_operations_json ?: $row->available_operations ?: []);
            if (! in_array($operation, $ops, true)) {
                continue;
            }

            $provider = strtolower((string) ($row->provider ?: $row->provider_code));
            if ($provider !== '' && ! in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    private function activeModuleForServiceType(string $serviceType): ?ServiceModule
    {
        if (! Schema::hasTable('service_modules')) {
            return null;
        }

        return ServiceModule::query()
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }

}

