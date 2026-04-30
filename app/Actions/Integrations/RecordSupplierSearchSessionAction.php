<?php

namespace App\Actions\Integrations;

use App\Models\SupplierSearchSession;

/**
 * Persists normalized search session + optional offer rows (business snapshot, not raw wire format).
 */
final class RecordSupplierSearchSessionAction
{
    /**
     * @param  array<string, mixed>|null  $internalRequestSnapshot  e.g. FlightSearchRequestData as array
     * @param  array<string, mixed>|null  $searchResultsSummary      counts, route keys, etc.
     */
    public function execute(
        string $correlationId,
        string $provider,
        string $environment,
        string $status,
        ?array $internalRequestSnapshot = null,
        ?array $searchResultsSummary = null,
        ?int $integrationConnectionId = null,
        ?string $startedAt = null,
        ?string $completedAt = null
    ): SupplierSearchSession {
        return SupplierSearchSession::query()->updateOrCreate(
            ['correlation_id' => $correlationId],
            [
                'integration_connection_id' => $integrationConnectionId,
                'provider' => $provider,
                'environment' => $environment,
                'internal_request_snapshot' => $internalRequestSnapshot,
                'search_results_summary' => $searchResultsSummary,
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]
        );
    }
}
