<?php

namespace App\Services\Integrations;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lightweight orchestration / audit trail for integration flows.
 */
final class IntegrationAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function logOrchestration(string $logType, string $provider, array $context = []): void
    {
        if (! Schema::hasTable('integration_logs')) {
            return;
        }

        IntegrationLog::query()->create([
            'provider' => $provider,
            'correlation_id' => $context['correlation_id'] ?? Str::uuid()->toString(),
            'log_type' => $logType,
            'payload' => $context,
            'created_at' => now(),
        ]);
    }
}
