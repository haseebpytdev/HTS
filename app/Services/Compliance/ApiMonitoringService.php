<?php

namespace App\Services\Compliance;

use App\Models\IntegrationLog;

class ApiMonitoringService
{
    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $since = now()->subDay();
        $logs = IntegrationLog::query()->where('created_at', '>=', $since)->get();

        $total = $logs->count();
        $failed = $logs->filter(fn ($log) => str_contains((string) $log->log_type, 'fail'))->count();
        $success = max(0, $total - $failed);
        $errorRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

        $byProvider = $logs->groupBy('provider')->map(fn ($items, $provider) => [
            'provider' => $provider,
            'total' => $items->count(),
            'failed' => $items->filter(fn ($log) => str_contains((string) $log->log_type, 'fail'))->count(),
        ])->values()->all();

        return [
            'window' => '24h',
            'total_calls' => $total,
            'success_calls' => $success,
            'failed_calls' => $failed,
            'error_rate_percent' => $errorRate,
            'by_provider' => $byProvider,
        ];
    }
}
