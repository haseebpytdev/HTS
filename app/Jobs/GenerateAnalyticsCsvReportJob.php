<?php

namespace App\Jobs;

use App\Models\AsyncTaskRun;
use App\Services\Analytics\AdminAnalyticsService;
use App\Services\Async\AsyncTaskTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAnalyticsCsvReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{from_date: string, to_date: string}  $range
     */
    public function __construct(
        public readonly int $taskRunId,
        public readonly array $range,
    ) {
    }

    public function handle(AdminAnalyticsService $analytics, AsyncTaskTracker $tracker): void
    {
        $task = AsyncTaskRun::query()->find($this->taskRunId);
        if (! $task) {
            return;
        }
        $tracker->markRunning($task);

        try {
            $agencies = $analytics->agencyReportRows($this->range['from_date'], $this->range['to_date']);
            $packages = $analytics->packageReportRows($this->range['from_date'], $this->range['to_date']);
            $file = 'reports/analytics/analytics-bundle-'.$task->id.'-'.now()->format('YmdHis').'.csv';

            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, ['section', 'id', 'name', 'metric_1', 'metric_2', 'metric_3', 'metric_4']);
            foreach ($agencies as $row) {
                fputcsv($csv, ['agency', $row['agency_id'], $row['name'], $row['bookings_count'], $row['confirmed_bookings_count'], $row['supplier_cost_sum'], $row['net_margin_sum']]);
            }
            foreach ($packages as $row) {
                fputcsv($csv, ['package', $row['package_id'], $row['title'], $row['inquiries_count'], $row['quoted_inquiries_count'], $row['confirmed_inquiries_count'], $row['potential_value_sum']]);
            }
            rewind($csv);
            $contents = stream_get_contents($csv);
            fclose($csv);

            Storage::disk('local')->put($file, (string) $contents);
            $tracker->markCompleted($task, [
                'disk' => 'local',
                'path' => $file,
                'file_name' => basename($file),
            ]);
        } catch (Throwable $e) {
            $tracker->markFailed($task, $e->getMessage());
        }
    }
}
