<?php

namespace App\Repositories;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;

final class IntegrationConnectionRepository
{
    /**
     * Resolve active healthy connection in order:
     * 1) tenant-owned for current tenant
     * 2) platform/global owned
     */
    public function findActiveForTenantProviderAndEnvironment(
        string $provider,
        string $environment,
        ?int $tenantId
    ): ?IntegrationConnection {
        $environmentAliases = $this->environmentAliases($environment);
        $baseQuery = IntegrationConnection::query()
            ->where(function ($query) use ($provider): void {
                if (AmadeusSelfServiceProvider::matches($provider)) {
                    $query->whereIn('provider', AmadeusSelfServiceProvider::aliases());
                } else {
                    $query->where('provider', $provider);
                }
            })
            ->whereIn('environment', $environmentAliases)
            ->where('is_active', true)
            ->whereIn('status', ['healthy', 'connected']);
        $baseQuery = $this->applyRuntimePriorityOrdering($baseQuery);

        if ($tenantId !== null) {
            $tenantOwned = (clone $baseQuery)
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->where('ownership_type', 'tenant')
                        ->orWhereNull('ownership_type');
                })
                ->first();
            if ($tenantOwned !== null) {
                return $tenantOwned;
            }
        }

        return (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNull('tenant_id')
                    ->orWhere('ownership_type', 'platform_owner');
            })
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 0 ELSE 1 END ASC')
            ->first();
    }

    public function findActiveForProviderAndEnvironment(string $provider, string $environment): ?IntegrationConnection
    {
        $query = IntegrationConnection::query()
            ->where(function ($query) use ($provider): void {
                if (AmadeusSelfServiceProvider::matches($provider)) {
                    $query->whereIn('provider', AmadeusSelfServiceProvider::aliases());
                } else {
                    $query->where('provider', $provider);
                }
            })
            ->whereIn('environment', $this->environmentAliases($environment))
            ->where('is_active', true)
            ->whereIn('status', ['healthy', 'connected']);
        $query = $this->applyRuntimePriorityOrdering($query);

        return $query->first();
    }

    /**
     * @return list<string>
     */
    private function environmentAliases(string $environment): array
    {
        $normalized = strtolower(trim($environment));

        return match ($normalized) {
            'sandbox', 'test', 'testing', 'development' => ['sandbox', 'test', 'testing', 'development'],
            'production', 'live' => ['production', 'live'],
            default => [$normalized],
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<IntegrationConnection>  $query
     * @return \Illuminate\Database\Eloquent\Builder<IntegrationConnection>
     */
    private function applyRuntimePriorityOrdering($query)
    {
        return $query
            ->orderByDesc('is_default')
            ->orderByRaw("CASE WHEN status = 'healthy' THEN 0 WHEN status = 'connected' THEN 1 ELSE 2 END ASC")
            ->orderByRaw('CASE WHEN last_success_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('last_success_at')
            ->orderByRaw('CASE WHEN last_tested_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('last_tested_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }
}
