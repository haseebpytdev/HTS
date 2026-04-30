<?php

namespace App\Services\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\Agency;
use App\Models\IntegrationConnection;
use App\Models\IntegrationRequestLog;
use App\Models\ServiceModule;
use App\Models\Tenant;
use App\Models\TenantProviderAccess;
use App\Repositories\IntegrationConnectionRepository;
use Illuminate\Support\Carbon;

final class TenantProviderAuthorizationService
{
    public function __construct(
        private readonly TenantProviderAccessService $accessMatrix,
        private readonly IntegrationConnectionRepository $connections,
        private readonly ?IntegrationAuditLogger $auditLogger = null,
    ) {
    }

    public function authorizeProvider(
        ?int $tenantId,
        ?int $agencyId,
        string $provider,
        string $operation
    ): void {
        $tenant = $this->resolveTenant($tenantId, $agencyId);
        if ($tenant === null) {
            throw $this->forbidden('tenant_not_found', 'Tenant could not be resolved for integration request.');
        }

        $rows = $this->accessMatrix->matrixForTenant($tenant);
        $row = collect($rows)->firstWhere('provider', strtolower($provider));
        if (! is_array($row)) {
            throw $this->forbidden('provider_not_allowed', 'Provider is not allowed for this tenant.');
        }
        if (! (bool) ($row['is_enabled'] ?? false)) {
            throw $this->forbidden('provider_disabled', 'Provider is disabled for this tenant.');
        }

        $operationKey = match ($operation) {
            'search' => 'can_search',
            'pricing' => 'can_price',
            'booking' => 'can_book',
            default => null,
        };

        if ($operationKey === null || ! (bool) ($row[$operationKey] ?? false)) {
            throw $this->forbidden('operation_not_allowed', ucfirst($operation).' is not allowed for this tenant/provider.');
        }

        $this->enforceProviderQuotaLimits($tenant->id, strtolower($provider), $operation, $row);

        $module = ServiceModule::query()
            ->where('service_type', 'Flights')
            ->where(function ($q) use ($provider): void {
                $q->where('provider', strtolower($provider))
                    ->orWhere('provider_code', strtolower($provider));
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if ($module !== null) {
            $supportedOps = (array) ($module->supported_operations_json ?: $module->available_operations ?: []);
            if (! (bool) $module->is_active) {
                throw $this->unavailable('module_inactive', 'Provider module is inactive.');
            }
            if (! in_array($operation, $supportedOps, true)) {
                throw $this->unavailable('module_operation_disabled', 'Provider module does not allow requested operation.');
            }
            if (in_array((string) $module->status, ['misconfigured', 'inactive'], true)) {
                throw $this->unavailable('module_misconfigured', 'Provider module is misconfigured.');
            }
            if (in_array((string) $module->connection_status, ['failed', 'disconnected', 'warning'], true)) {
                throw $this->unavailable('module_unhealthy', 'Provider module connection is unhealthy.');
            }
        }

        $expectedEnv = $this->expectedConnectionEnvironmentForProvider($tenant->id, $provider);
        $connection = $this->resolveConnection($tenant->id, strtolower($provider), $expectedEnv);
        if ($connection === null) {
            throw $this->unavailable('connection_not_found', 'No active supplier connection found for the required environment.');
        }
        if (! $connection->is_active) {
            throw $this->unavailable('connection_inactive', 'Supplier connection is inactive.');
        }
        if (($connection->status ?? 'untested') !== 'healthy') {
            throw $this->unavailable('connection_unhealthy', 'Supplier connection is not healthy.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function enforceProviderQuotaLimits(int $tenantId, string $provider, string $operation, array $row): void
    {
        $usageQuota = is_array($row['usage_quota_json'] ?? null) ? $row['usage_quota_json'] : [];
        $softLimitPercent = min(100, max(1, (int) ($row['soft_limit_percent'] ?? 80)));
        $hardLimitEnforced = (bool) ($row['hard_limit_enforced'] ?? false);
        $alertEnabled = (bool) ($row['overage_alert_enabled'] ?? true);

        if (strtolower($operation) === 'booking') {
            $monthlyQuota = max(0, (int) ($usageQuota['bookings_monthly'] ?? 0));
            if ($monthlyQuota > 0) {
                $used = $this->providerUsageCount(
                    tenantId: $tenantId,
                    provider: $provider,
                    operation: 'booking',
                    windowStart: now()->startOfMonth()
                );
                $this->enforceQuotaThresholds(
                    provider: $provider,
                    operation: 'booking',
                    limit: $monthlyQuota,
                    used: $used,
                    softLimitPercent: $softLimitPercent,
                    hardLimitEnforced: $hardLimitEnforced,
                    alertEnabled: $alertEnabled,
                    window: 'monthly'
                );
            }

            return;
        }

        $dailyQuota = max(0, (int) ($usageQuota['searches_daily'] ?? 0));
        if ($dailyQuota <= 0) {
            return;
        }

        $used = $this->providerUsageCount(
            tenantId: $tenantId,
            provider: $provider,
            operation: $operation,
            windowStart: now()->startOfDay()
        );
        $this->enforceQuotaThresholds(
            provider: $provider,
            operation: $operation,
            limit: $dailyQuota,
            used: $used,
            softLimitPercent: $softLimitPercent,
            hardLimitEnforced: $hardLimitEnforced,
            alertEnabled: $alertEnabled,
            window: 'daily'
        );
    }

    private function enforceQuotaThresholds(
        string $provider,
        string $operation,
        int $limit,
        int $used,
        int $softLimitPercent,
        bool $hardLimitEnforced,
        bool $alertEnabled,
        string $window
    ): void {
        $next = $used + 1;
        $softThreshold = (int) ceil($limit * ($softLimitPercent / 100));
        if ($hardLimitEnforced && $next > $limit) {
            throw $this->forbidden(
                'provider_quota_exceeded',
                ucfirst($operation).' quota exceeded for provider '.$provider.' ('.$window.' limit: '.$limit.').'
            );
        }

        if ($alertEnabled && $next >= max(1, $softThreshold) && $this->auditLogger !== null) {
            $this->auditLogger->logOrchestration('tenant_provider_quota_soft_limit_reached', $provider, [
                'provider' => $provider,
                'operation' => $operation,
                'window' => $window,
                'limit' => $limit,
                'used' => $used,
                'next' => $next,
                'soft_limit_percent' => $softLimitPercent,
            ]);
        }
    }

    private function providerUsageCount(int $tenantId, string $provider, string $operation, Carbon $windowStart): int
    {
        $connectionIds = IntegrationConnection::query()
            ->where('provider', $provider)
            ->where(function ($q) use ($tenantId): void {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->pluck('id')
            ->all();
        if ($connectionIds === []) {
            return 0;
        }

        return IntegrationRequestLog::query()
            ->where('provider', $provider)
            ->whereIn('integration_connection_id', $connectionIds)
            ->whereIn('operation', $this->operationsForUsageCounter($operation))
            ->where('created_at', '>=', $windowStart)
            ->distinct('correlation_id')
            ->count('correlation_id');
    }

    /**
     * @return list<string>
     */
    private function operationsForUsageCounter(string $operation): array
    {
        return match (strtolower($operation)) {
            'search' => ['search', 'search_offer_request', 'search_offers'],
            'pricing' => ['pricing', 'pricing_soap'],
            'booking' => ['booking', 'booking_create', 'booking_soap_create'],
            default => [$operation],
        };
    }

    private function resolveTenant(?int $tenantId, ?int $agencyId): ?Tenant
    {
        if ($tenantId !== null) {
            return Tenant::query()->find($tenantId);
        }
        if ($agencyId !== null) {
            $resolvedTenantId = Agency::query()->whereKey($agencyId)->value('tenant_id');
            if ($resolvedTenantId !== null) {
                return Tenant::query()->find($resolvedTenantId);
            }
        }

        $defaultTenantId = Tenant::defaultId();

        return $defaultTenantId !== null ? Tenant::query()->find($defaultTenantId) : null;
    }

    private function expectedConnectionEnvironment(): string
    {
        $cfg = strtolower((string) config('integrations.credential_environment', 'production'));

        return $cfg === 'test' ? 'sandbox' : 'production';
    }

    private function expectedConnectionEnvironmentForProvider(int $tenantId, string $provider): string
    {
        $providerCode = strtolower($provider);
        $accessRow = TenantProviderAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', $providerCode)
            ->orderBy('priority_order')
            ->first();
        if ($accessRow !== null) {
            $accessEnvironment = $this->normalizeRuntimeEnvironment((string) ($accessRow->environment ?? ''));
            if ($accessEnvironment !== null) {
                return $accessEnvironment;
            }
        }

        $module = ServiceModule::query()
            ->where('service_type', 'Flights')
            ->where(function ($q) use ($provider): void {
                $q->where('provider', strtolower($provider))
                    ->orWhere('provider_code', strtolower($provider));
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        $moduleConfig = is_array($module?->config) ? $module->config : [];
        $integrationEnv = $this->normalizeRuntimeEnvironment((string) ($moduleConfig['integration_env'] ?? ''));
        if ($integrationEnv !== null) {
            return $integrationEnv;
        }

        $moduleEnvironment = $this->normalizeRuntimeEnvironment((string) ($module?->environment ?? ''));
        if ($moduleEnvironment !== null) {
            return $moduleEnvironment;
        }

        return $this->expectedConnectionEnvironment();
    }

    private function normalizeRuntimeEnvironment(string $environment): ?string
    {
        $normalized = strtolower(trim($environment));

        return match ($normalized) {
            'sandbox', 'test', 'testing', 'development' => 'sandbox',
            'production', 'live' => 'production',
            default => null,
        };
    }

    private function resolveConnection(int $tenantId, string $provider, string $environment): ?IntegrationConnection
    {
        return $this->connections->findActiveForTenantProviderAndEnvironment(
            provider: $provider,
            environment: $environment,
            tenantId: $tenantId
        );
    }

    private function forbidden(string $code, string $message): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'TENANT_PROVIDER_FORBIDDEN',
            normalizedCode: 'integration_access_denied',
            supplierContext: ['policy_code' => $code],
            apiError: new ApiErrorData(
                code: 'integration_access_denied',
                message: $message,
                supplierCode: 'TENANT_PROVIDER_FORBIDDEN',
                httpStatus: 403,
            ),
        );
    }

    private function unavailable(string $code, string $message): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'TENANT_PROVIDER_UNAVAILABLE',
            normalizedCode: 'integration_provider_unavailable',
            supplierContext: ['availability_code' => $code],
            apiError: new ApiErrorData(
                code: 'integration_provider_unavailable',
                message: $message,
                supplierCode: 'TENANT_PROVIDER_UNAVAILABLE',
                httpStatus: 503,
            ),
        );
    }
}
