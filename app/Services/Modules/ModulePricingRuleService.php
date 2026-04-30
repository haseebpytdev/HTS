<?php

namespace App\Services\Modules;

use App\Models\ServiceModule;
use App\Models\ServiceModulePricingRule;
use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Support\Facades\Cache;

class ModulePricingRuleService
{
    public function __construct(
        private readonly ComplianceAuditService $audit,
    ) {
    }

    public function update(string $moduleKey, string $b2bType, float $b2bValue, string $b2cType, float $b2cValue, string $baseCurrency, ?int $actorUserId = null): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();

        ServiceModulePricingRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            [
                'markup_type_b2b' => $b2bType,
                'markup_value_b2b' => $b2bValue,
                'markup_type_b2c' => $b2cType,
                'markup_value_b2c' => $b2cValue,
                'base_currency_code' => strtoupper($baseCurrency),
            ]
        );

        Cache::forget('module.rules.'.$moduleKey);
        Cache::forget('pricing.rules.'.$moduleKey);

        $this->audit->record(
            area: 'modules',
            action: 'module_pricing_updated',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: 'info',
            context: [
                'b2b_type' => $b2bType,
                'b2b_value' => $b2bValue,
                'b2c_type' => $b2cType,
                'b2c_value' => $b2cValue,
                'base_currency' => strtoupper($baseCurrency),
                'actor_user_id' => $actorUserId,
            ],
        );
    }
}
