<?php

namespace App\Services\Tenancy;

use App\Models\ServiceModule;
use App\Models\Tenant;
use App\Models\TenantModuleAccess;
use App\Models\TenantProviderAccess;
use App\Services\Compliance\ComplianceAuditService;

final class TenantPlanService
{
    public function __construct(
        private readonly ComplianceAuditService $audit,
    ) {
    }

    /**
     * @return list<Tenant>
     */
    public function listTenants(): array
    {
        return Tenant::query()->orderBy('name')->get()->all();
    }

    /**
     * @return array{enabled_modules:int, enabled_providers:int}
     */
    public function governanceSummary(Tenant $tenant): array
    {
        $enabledModules = TenantModuleAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_enabled', true)
            ->count();
        $enabledProviders = TenantProviderAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_enabled', true)
            ->count();

        return [
            'enabled_modules' => (int) $enabledModules,
            'enabled_providers' => (int) $enabledProviders,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTenantPlan(Tenant $tenant, array $payload): void
    {
        $tenant->forceFill([
            'plan_tier' => (string) ($payload['plan_tier'] ?? $tenant->plan_tier ?? 'basic'),
            'usage_quota_json' => [
                'bookings_monthly' => (int) ($payload['bookings_monthly_quota'] ?? 0),
                'searches_daily' => (int) ($payload['searches_daily_quota'] ?? 0),
            ],
            'soft_limit_percent' => (int) ($payload['soft_limit_percent'] ?? 80),
            'hard_limit_enforced' => (bool) ($payload['hard_limit_enforced'] ?? false),
            'overage_alert_enabled' => (bool) ($payload['overage_alert_enabled'] ?? true),
        ])->save();

        $this->audit->record(
            area: 'tenancy',
            action: 'tenant_plan_updated',
            entityType: Tenant::class,
            entityId: $tenant->id,
            severity: 'info',
            context: [
                'plan_tier' => $tenant->plan_tier,
                'soft_limit_percent' => $tenant->soft_limit_percent,
                'hard_limit_enforced' => $tenant->hard_limit_enforced,
                'overage_alert_enabled' => $tenant->overage_alert_enabled,
            ],
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function moduleAccessMatrix(Tenant $tenant): array
    {
        $modules = ServiceModule::query()->orderBy('service_type')->orderBy('name')->get();
        $existing = TenantModuleAccess::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('module_code');

        $rows = [];
        foreach ($modules as $module) {
            $code = (string) ($module->code ?: $module->module_key);
            $current = $existing->get($code);
            $rows[] = [
                'module_code' => $code,
                'module_name' => (string) ($module->name ?: $module->provider_name ?: $code),
                'service_type' => (string) $module->service_type,
                'is_enabled' => (bool) ($current?->is_enabled ?? false),
                'allowed_operations' => (array) ($current?->allowed_operations ?? ['search', 'pricing', 'booking']),
                'usage_quota_json' => (array) ($current?->usage_quota_json ?? ['bookings_monthly' => 0, 'searches_daily' => 0]),
                'soft_limit_percent' => (int) ($current?->soft_limit_percent ?? 80),
                'hard_limit_enforced' => (bool) ($current?->hard_limit_enforced ?? false),
                'overage_alert_enabled' => (bool) ($current?->overage_alert_enabled ?? true),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function saveModuleAccessMatrix(Tenant $tenant, array $rows): void
    {
        foreach ($rows as $row) {
            $code = (string) ($row['module_code'] ?? '');
            if ($code === '') {
                continue;
            }

            TenantModuleAccess::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_code' => $code],
                [
                    'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                    'allowed_operations' => array_values((array) ($row['allowed_operations'] ?? [])),
                    'usage_quota_json' => [
                        'bookings_monthly' => (int) ($row['bookings_monthly_quota'] ?? 0),
                        'searches_daily' => (int) ($row['searches_daily_quota'] ?? 0),
                    ],
                    'soft_limit_percent' => (int) ($row['soft_limit_percent'] ?? 80),
                    'hard_limit_enforced' => (bool) ($row['hard_limit_enforced'] ?? false),
                    'overage_alert_enabled' => (bool) ($row['overage_alert_enabled'] ?? true),
                ]
            );
        }

        $this->audit->record(
            area: 'tenancy',
            action: 'tenant_module_access_updated',
            entityType: Tenant::class,
            entityId: $tenant->id,
            severity: 'info',
            context: ['rows' => count($rows)],
        );
    }
}
