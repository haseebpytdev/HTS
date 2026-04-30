<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin-side audit trail for integration connections (who / when / provider / tenant).
 * Stored in {@see \App\Models\IntegrationLog} with log_type prefix `admin.integration.*`.
 */
final class IntegrationConnectionAdminAuditService
{
    public function __construct(
        private readonly IntegrationAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logConnectionAction(IntegrationConnection $connection, string $action, ?int $actorUserId, array $context = []): void
    {
        $this->write(
            logType: 'admin.integration.connection.'.$action,
            provider: (string) $connection->provider,
            context: array_merge([
                'actor_user_id' => $actorUserId,
                'tenant_id' => $connection->tenant_id,
                'integration_connection_id' => $connection->id,
                'environment' => $connection->environment,
                'account_name' => $connection->account_name ?? $connection->name,
            ], $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logSupplierAccount(string $action, string $provider, ?int $tenantId, string $accountKey, ?int $actorUserId, array $context = []): void
    {
        $this->write(
            logType: 'admin.integration.supplier_account.'.$action,
            provider: $provider,
            context: array_merge([
                'actor_user_id' => $actorUserId,
                'tenant_id' => $tenantId,
                'account_key' => $accountKey,
            ], $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logAccessMatrix(Tenant $tenant, ?int $actorUserId, array $context = []): void
    {
        $this->write(
            logType: 'admin.integration.access_matrix.updated',
            provider: 'platform',
            context: array_merge([
                'actor_user_id' => $actorUserId,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ], $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $logType, string $provider, array $context): void
    {
        if (! Schema::hasTable('integration_logs')) {
            return;
        }

        $this->auditLogger->logOrchestration($logType, $provider, array_merge($context, [
            'correlation_id' => $context['correlation_id'] ?? Str::uuid()->toString(),
        ]));
    }
}
