<?php

namespace App\Services\Integrations;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Schema;

final class ProviderHealthScoringService
{
    /**
     * @param  list<string>  $drivers
     * @return array<string, array{score: float, success_rate: float, avg_latency_ms: float|null, sample_size: int}>
     */
    public function scoreDrivers(array $drivers): array
    {
        $scores = [];
        if (! Schema::hasTable('integration_logs')) {
            foreach ($drivers as $driver) {
                $scores[$driver] = $this->defaultScore();
            }

            return $scores;
        }

        $windowMinutes = max(1, (int) config('integrations.provider_health_window_minutes', 60));
        $since = now()->subMinutes($windowMinutes);
        $successWeight = (float) config('integrations.provider_health_success_weight', 0.7);
        $latencyWeight = (float) config('integrations.provider_health_latency_weight', 0.3);

        $rows = IntegrationLog::query()
            ->whereIn('provider', $drivers)
            ->whereIn('log_type', ['flight_search_completed', 'flight_search_failed'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->get(['provider', 'log_type', 'payload']);

        foreach ($drivers as $driver) {
            $providerRows = $rows->where('provider', $driver)->values();
            $sampleSize = $providerRows->count();

            if ($sampleSize === 0) {
                $scores[$driver] = $this->defaultScore();
                continue;
            }

            $successCount = $providerRows->where('log_type', 'flight_search_completed')->count();
            $successRate = $successCount / $sampleSize;

            $latencies = $providerRows
                ->map(static function ($row): ?float {
                    $payload = is_array($row->payload) ? $row->payload : [];
                    $latency = $payload['latency_ms'] ?? null;

                    return is_numeric($latency) ? (float) $latency : null;
                })
                ->filter(static fn (?float $x): bool => $x !== null)
                ->values();

            $avgLatency = $latencies->count() > 0 ? (float) ($latencies->sum() / $latencies->count()) : null;
            $latencyScore = $avgLatency === null ? 0.5 : 1 / (1 + ($avgLatency / 1000));
            $score = ($successRate * $successWeight) + ($latencyScore * $latencyWeight);

            $scores[$driver] = [
                'score' => round($score, 6),
                'success_rate' => round($successRate, 6),
                'avg_latency_ms' => $avgLatency !== null ? round($avgLatency, 2) : null,
                'sample_size' => $sampleSize,
            ];
        }

        return $scores;
    }

    /**
     * @param  list<string>  $drivers
     * @return list<string>
     */
    public function rankDrivers(array $drivers): array
    {
        $scores = $this->scoreDrivers($drivers);

        usort($drivers, static function (string $a, string $b) use ($scores): int {
            $left = $scores[$a]['score'] ?? 0.5;
            $right = $scores[$b]['score'] ?? 0.5;

            if ($left === $right) {
                return strcmp($a, $b);
            }

            return $left < $right ? 1 : -1;
        });

        return $drivers;
    }

    /**
     * @return array{score: float, success_rate: float, avg_latency_ms: float|null, sample_size: int}
     */
    private function defaultScore(): array
    {
        return [
            'score' => 0.5,
            'success_rate' => 0.5,
            'avg_latency_ms' => null,
            'sample_size' => 0,
        ];
    }
}
