<?php

namespace App\Services\Compliance;

use App\Models\ComplianceAuditLog;
use Illuminate\Http\Request;

class ComplianceAuditService
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function record(
        string $area,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $severity = 'info',
        array $context = [],
        ?Request $request = null
    ): void {
        ComplianceAuditLog::query()->create([
            'actor_user_id' => auth()->id(),
            'area' => $area,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity,
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'correlation_id' => $request?->header('X-Correlation-Id'),
            'created_at' => now(),
        ]);
    }
}
