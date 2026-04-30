<?php

namespace App\Services\Modules;

use App\Models\ServiceModule;
use App\Models\ServiceModuleTaxRule;
use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Support\Facades\Cache;

class ModuleTaxRuleService
{
    public function __construct(
        private readonly ComplianceAuditService $audit,
    ) {
    }

    public function update(string $moduleKey, string $taxType, float $taxValue, ?int $currencyId = null, ?int $actorUserId = null): void
    {
        $module = ServiceModule::query()->where('code', $moduleKey)->orWhere('module_key', $moduleKey)->firstOrFail();

        ServiceModuleTaxRule::query()->updateOrCreate(
            ['service_module_id' => $module->id],
            [
                'tax_type' => $taxType,
                'tax_value' => $taxValue,
                'currency_id' => $currencyId,
            ]
        );

        Cache::forget('module.rules.'.$moduleKey);
        Cache::forget('tax.rules.'.$moduleKey);

        $this->audit->record(
            area: 'modules',
            action: 'module_tax_updated',
            entityType: ServiceModule::class,
            entityId: $module->id,
            severity: 'info',
            context: [
                'tax_type' => $taxType,
                'tax_value' => $taxValue,
                'currency_id' => $currencyId,
                'actor_user_id' => $actorUserId,
            ],
        );
    }
}
