<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordExportHistoryAction;
use App\Jobs\GenerateAnalyticsCsvReportJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAnalyticsRequest;
use App\Models\AsyncTaskRun;
use App\Models\ExportHistory;
use App\Services\Analytics\AdminAnalyticsService;
use App\Services\Async\AsyncTaskTracker;
use App\Services\Finance\FinanceSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsService $analytics,
        private readonly RecordExportHistoryAction $recordExportHistoryAction,
        private readonly AsyncTaskTracker $taskTracker,
        private readonly FinanceSettingsService $financeSettings,
    ) {
    }

    public function index(FilterAnalyticsRequest $request): View
    {
        $this->authorize('access-admin-area');

        $controls = $this->financeSettings->all();
        $fromInput = $request->validated('from_date');
        $toInput = $request->validated('to_date');
        if (($fromInput === null || $fromInput === '') && ($toInput === null || $toInput === '')) {
            $windowDays = max(1, (int) ($controls['revenue_report_default_window_days'] ?? 30));
            $fromInput = now()->subDays($windowDays - 1)->toDateString();
            $toInput = now()->toDateString();
        }

        $range = $this->analytics->resolveDateRange(
            $fromInput,
            $toInput,
        );

        $kpis = $this->analytics->dashboardKpis($range['from_date'], $range['to_date']);
        $reports = $this->analytics->reports($range['from_date'], $range['to_date']);
        $trends = $this->analytics->trends($range['from_date'], $range['to_date']);

        return view('admin.analytics.index', [
            'range' => $range,
            'kpis' => $kpis,
            'agencyRows' => $reports['agency_rows'],
            'packageRows' => $reports['package_rows'],
            'financials' => $reports['financials'],
            'paymentAging' => $reports['payment_aging'],
            'trends' => $trends,
            'financeControls' => $controls,
            'reportTasks' => AsyncTaskRun::query()
                ->where('task_type', 'analytics_csv_bundle')
                ->latest('id')
                ->limit(10)
                ->get(),
            'supplierRetryTasks' => AsyncTaskRun::query()
                ->where('task_type', 'supplier_search_retry')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function queueBundleCsv(FilterAnalyticsRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $range = $this->analytics->resolveDateRange($request->validated('from_date'), $request->validated('to_date'));
        $task = $this->taskTracker->create(
            taskType: 'analytics_csv_bundle',
            requestedByUserId: (int) auth()->id(),
            payload: $range
        );

        GenerateAnalyticsCsvReportJob::dispatch($task->id, $range);

        return redirect()->route('admin.analytics.index', $range)->with('success', 'Analytics bundle queued in background.');
    }

    public function downloadTaskReport(AsyncTaskRun $taskRun): StreamedResponse
    {
        $this->authorize('access-admin-area');
        abort_if($taskRun->task_type !== 'analytics_csv_bundle', 404);
        abort_unless(($taskRun->status === 'completed') && is_array($taskRun->result), 404);

        $disk = (string) ($taskRun->result['disk'] ?? 'local');
        $path = (string) ($taskRun->result['path'] ?? '');
        abort_if($path === '' || ! Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, (string) ($taskRun->result['file_name'] ?? basename($path)));
    }

    public function agenciesCsv(FilterAnalyticsRequest $request): StreamedResponse
    {
        $this->authorize('access-admin-area');
        $range = $this->analytics->resolveDateRange($request->validated('from_date'), $request->validated('to_date'));
        $rows = $this->analytics->agencyReportRows($range['from_date'], $range['to_date']);
        $fileName = 'analytics-agencies-'.now()->format('Ymd_His').'.csv';
        $this->recordExportHistoryAction->execute((int) auth()->id(), ExportHistory::TYPE_BOOKINGS_CSV, null, ['file_name' => $fileName, 'scope' => 'analytics_agency']);

        return response()->stream(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['agency_id', 'agency_name', 'bookings', 'confirmed_bookings', 'booking_value_sum', 'collected_revenue_sum', 'supplier_cost_sum', 'net_margin_sum', 'net_margin_percent']);
            foreach ($rows as $row) {
                fputcsv($h, [
                    $row['agency_id'],
                    $row['name'],
                    $row['bookings_count'],
                    $row['confirmed_bookings_count'],
                    $row['booking_value_sum'],
                    $row['collected_revenue_sum'],
                    $row['supplier_cost_sum'],
                    $row['net_margin_sum'],
                    $row['net_margin_percent'],
                ]);
            }
            fclose($h);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$fileName}"]);
    }

    public function packagesCsv(FilterAnalyticsRequest $request): StreamedResponse
    {
        $this->authorize('access-admin-area');
        $range = $this->analytics->resolveDateRange($request->validated('from_date'), $request->validated('to_date'));
        $rows = $this->analytics->packageReportRows($range['from_date'], $range['to_date']);
        $fileName = 'analytics-packages-'.now()->format('Ymd_His').'.csv';
        $this->recordExportHistoryAction->execute((int) auth()->id(), ExportHistory::TYPE_INQUIRIES_CSV, null, ['file_name' => $fileName, 'scope' => 'analytics_package']);

        return response()->stream(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['package_id', 'package_title', 'inquiries', 'quoted_inquiries', 'confirmed_inquiries', 'potential_value_sum']);
            foreach ($rows as $row) {
                fputcsv($h, [$row['package_id'], $row['title'], $row['inquiries_count'], $row['quoted_inquiries_count'], $row['confirmed_inquiries_count'], $row['potential_value_sum']]);
            }
            fclose($h);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$fileName}"]);
    }
}
