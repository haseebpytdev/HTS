<?php

namespace App\Services\Admin;

use App\Enums\PaymentRecordStatus;
use App\Models\AsyncTaskRun;
use App\Models\BookingDocument;
use App\Models\IntegrationLog;
use App\Models\PaymentGatewayTransaction;
use App\Models\ServiceModuleHealthCheck;
use App\Models\SupportTicket;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HealthMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function healthOverview(): array
    {
        $summary = [
            'integration_health_unhealthy' => (int) ServiceModuleHealthCheck::query()
                ->whereIn('result_status', ['critical', 'failed', 'warning'])
                ->where('checked_at', '>=', now()->subDay())
                ->count(),
            'failed_provider_tests' => (int) ServiceModuleHealthCheck::query()
                ->whereIn('result_status', ['critical', 'failed'])
                ->where('checked_at', '>=', now()->subDay())
                ->count(),
            'recent_api_errors' => $this->recentApiErrorsCount(),
            'queue_failures_24h' => $this->failedJobsCount24h(),
            'scheduler_last_run' => $this->schedulerLastRunAt(),
            'notification_failures_24h' => $this->notificationFailures24h(),
            'document_scan_failures' => $this->documentScanFailures(),
            'support_escalation_breaches' => $this->supportEscalationBreachesCount(),
            'payment_gateway_failures_24h' => (int) PaymentGatewayTransaction::query()
                ->where('status', PaymentRecordStatus::Failed->value)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        return [
            'summary' => $summary,
            'integration_health_by_provider' => $this->integrationHealthByProvider(),
            'failed_provider_tests_recent' => $this->failedProviderTestsRecent(),
            'recent_api_errors_list' => $this->recentApiErrorsList(),
            'queue_failures_recent' => $this->queueFailuresRecent(),
            'notification_failures_recent' => $this->notificationFailuresRecent(),
            'document_scan_failures_recent' => $this->documentScanFailuresRecent(),
            'support_escalation_breaches_recent' => $this->supportEscalationBreachesRecent(),
            'payment_gateway_failures_recent' => $this->paymentGatewayFailuresRecent(),
        ];
    }

    /**
     * @return LengthAwarePaginator<IntegrationLog>
     */
    public function integrationLogsBrowser(?string $provider = null): LengthAwarePaginator
    {
        $query = IntegrationLog::query()->latest('created_at');
        if ($provider !== null && trim($provider) !== '') {
            $query->where('provider', strtolower(trim($provider)));
        }

        return $query->paginate(50);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function asyncTaskMonitor(): array
    {
        $documentScanTasks = Schema::hasTable('async_task_runs')
            ? AsyncTaskRun::query()
                ->where('task_type', 'document_virus_scan')
                ->where('status', 'failed')
                ->latest('id')
                ->limit(30)
                ->get(['id', 'reference_type', 'reference_id', 'error_message', 'started_at', 'finished_at'])
            : collect();

        return [
            'jobs_backlog' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0,
            'failed_jobs_24h' => $this->failedJobsCount24h(),
            'job_batches_stale' => Schema::hasTable('job_batches')
                ? (int) DB::table('job_batches')->where('created_at', '<', now()->subDay()->timestamp)->count()
                : 0,
            'scheduler_last_run' => $this->schedulerLastRunAt(),
            'failed_jobs_recent' => $this->queueFailuresRecent(),
            'document_scan_task_failures' => $documentScanTasks,
            'scheduler_signal' => [
                'last_run' => $this->schedulerLastRunAt(),
                'stale_batch_count' => Schema::hasTable('job_batches')
                    ? (int) DB::table('job_batches')->where('created_at', '<', now()->subDay()->timestamp)->count()
                    : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function failedJobsAlertsBoard(): array
    {
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get()
            : collect();

        $failedProviderTests = ServiceModuleHealthCheck::query()
            ->whereIn('result_status', ['critical', 'failed'])
            ->latest('checked_at')
            ->limit(50)
            ->get(['service_module_id', 'result_status', 'message', 'checked_at']);

        $paymentFailures = PaymentGatewayTransaction::query()
            ->where('status', PaymentRecordStatus::Failed->value)
            ->latest('created_at')
            ->limit(50)
            ->get(['gateway_driver', 'external_id', 'amount', 'currency', 'created_at']);

        return [
            'failed_jobs' => $failedJobs,
            'failed_provider_tests' => $failedProviderTests,
            'payment_gateway_failures' => $paymentFailures,
            'notification_failures' => $this->notificationFailuresRecent(50),
            'document_scan_failures' => $this->documentScanFailuresRecent(50),
            'support_escalation_breaches' => $this->supportEscalationBreachesRecent(50),
            'scheduler_last_run' => $this->schedulerLastRunAt(),
        ];
    }

    private function recentApiErrorsCount(): int
    {
        if (! Schema::hasTable('integration_logs')) {
            return 0;
        }

        $query = DB::table('integration_logs')->where('created_at', '>=', now()->subDay());
        if (Schema::hasColumn('integration_logs', 'status_code')) {
            $query->whereIn('status_code', [500, 502, 503, 504]);
        } else {
            $query->where(function ($q): void {
                $q->where('log_type', 'error')
                    ->orWhere('log_type', 'failed');
            });
        }

        return (int) $query->count();
    }

    private function failedJobsCount24h(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
    }

    private function schedulerLastRunAt(): ?string
    {
        if (! Schema::hasTable('job_batches')) {
            return null;
        }

        $latest = DB::table('job_batches')->max('created_at');

        return is_numeric($latest) ? date('Y-m-d H:i:s', (int) $latest) : (is_string($latest) ? $latest : null);
    }

    private function notificationFailures24h(): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }
        if (! Schema::hasColumn('notifications', 'created_at')) {
            return 0;
        }

        return (int) DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    private function documentScanFailures(): int
    {
        if (! Schema::hasTable('booking_documents')) {
            return 0;
        }

        return (int) BookingDocument::query()
            ->whereIn('virus_scan_status', [BookingDocument::SCAN_FAILED, BookingDocument::SCAN_INFECTED, BookingDocument::SCAN_SUSPICIOUS])
            ->count();
    }

    private function supportEscalationBreaches(): int
    {
        return $this->supportEscalationBreachesCount();
    }

    /**
     * @return array<int, array{provider:string, unhealthy_count:int, latest_failure_at:string|null}>
     */
    private function integrationHealthByProvider(): array
    {
        if (! Schema::hasTable('service_module_health_checks') || ! Schema::hasTable('service_modules')) {
            return [];
        }

        return ServiceModuleHealthCheck::query()
            ->join('service_modules', 'service_modules.id', '=', 'service_module_health_checks.service_module_id')
            ->selectRaw("LOWER(COALESCE(service_modules.provider, service_modules.provider_code, 'unknown')) as provider, COUNT(*) as unhealthy_count, MAX(service_module_health_checks.checked_at) as latest_failure_at")
            ->whereIn('result_status', ['critical', 'failed', 'warning'])
            ->where('service_module_health_checks.checked_at', '>=', now()->subDay())
            ->groupBy(DB::raw("LOWER(COALESCE(service_modules.provider, service_modules.provider_code, 'unknown'))"))
            ->orderByDesc('unhealthy_count')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'provider' => (string) ($row->provider ?? 'unknown'),
                'unhealthy_count' => (int) ($row->unhealthy_count ?? 0),
                'latest_failure_at' => $this->stringDateTime($row->latest_failure_at ?? null),
            ])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServiceModuleHealthCheck>
     */
    private function failedProviderTestsRecent(): Collection
    {
        if (! Schema::hasTable('service_module_health_checks') || ! Schema::hasTable('service_modules')) {
            return collect();
        }

        return ServiceModuleHealthCheck::query()
            ->join('service_modules', 'service_modules.id', '=', 'service_module_health_checks.service_module_id')
            ->select([
                'service_module_health_checks.service_module_id',
                'service_modules.provider',
                'service_modules.provider_code',
                'service_module_health_checks.result_status',
                'service_module_health_checks.message',
                'service_module_health_checks.checked_at',
            ])
            ->whereIn('result_status', ['critical', 'failed'])
            ->latest('service_module_health_checks.checked_at')
            ->limit(25)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function recentApiErrorsList(): Collection
    {
        if (! Schema::hasTable('integration_logs')) {
            return collect();
        }

        $query = DB::table('integration_logs')
            ->select(['provider', 'log_type', 'correlation_id', 'payload', 'created_at'])
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at');

        if (Schema::hasColumn('integration_logs', 'status_code')) {
            $query->addSelect('status_code')
                ->whereIn('status_code', [500, 502, 503, 504]);
        } else {
            $query->whereIn('log_type', ['error', 'failed']);
        }

        return $query->limit(50)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function queueFailuresRecent(int $limit = 25): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->select(['id', 'queue', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function notificationFailuresRecent(int $limit = 25): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->select(['id', 'queue', 'exception', 'failed_at'])
            ->where(function ($q): void {
                $q->where('exception', 'like', '%Notification%')
                    ->orWhere('payload', 'like', '%notification%');
            })
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, BookingDocument>
     */
    private function documentScanFailuresRecent(int $limit = 25): Collection
    {
        if (! Schema::hasTable('booking_documents')) {
            return collect();
        }

        return BookingDocument::query()
            ->whereIn('virus_scan_status', [BookingDocument::SCAN_FAILED, BookingDocument::SCAN_INFECTED, BookingDocument::SCAN_SUSPICIOUS])
            ->latest('virus_scanned_at')
            ->limit($limit)
            ->get(['id', 'booking_id', 'virus_scan_status', 'virus_scan_note', 'virus_scanned_at']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SupportTicket>
     */
    private function supportEscalationBreachesRecent(int $limit = 25): Collection
    {
        if (! Schema::hasTable('support_tickets')) {
            return collect();
        }

        return SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->where(function ($query): void {
                if (Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                    $query->where('resolution_due_at', '<', now());
                } else {
                    $query->whereNotNull('escalated_at');
                }
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'ticket_number', 'subject', 'priority', 'status', 'resolution_due_at', 'escalated_at']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentGatewayTransaction>
     */
    private function paymentGatewayFailuresRecent(int $limit = 25): Collection
    {
        return PaymentGatewayTransaction::query()
            ->where('status', PaymentRecordStatus::Failed->value)
            ->latest('created_at')
            ->limit($limit)
            ->get(['gateway_driver', 'external_id', 'amount', 'currency', 'created_at']);
    }

    private function supportEscalationBreachesCount(): int
    {
        if (! Schema::hasTable('support_tickets')) {
            return 0;
        }

        $query = SupportTicket::query()->whereIn('status', ['open', 'in_progress']);
        if (Schema::hasColumn('support_tickets', 'priority')) {
            $query->whereIn('priority', ['high', 'urgent']);
        }
        if (Schema::hasColumn('support_tickets', 'resolution_due_at')) {
            $query->where('resolution_due_at', '<', now());
        } elseif (Schema::hasColumn('support_tickets', 'escalated_at')) {
            $query->whereNotNull('escalated_at');
        }

        return (int) $query->count();
    }

    private function stringDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
