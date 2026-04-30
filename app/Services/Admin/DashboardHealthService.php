<?php

namespace App\Services\Admin;

use App\Enums\PaymentRecordStatus;
use App\Models\ApprovalRequest;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\ExportHistory;
use App\Models\IntegrationConnection;
use App\Models\Payment;
use App\Models\PaymentGatewayTransaction;
use App\Models\ServiceModule;
use App\Models\ServiceModuleHealthCheck;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class DashboardHealthService
{
    /**
     * @return array<string, int>
     */
    public function executiveKpis(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return [
            'bookings_today' => (int) Booking::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'bookings_month' => (int) Booking::query()->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'revenue_today' => (int) Payment::query()
                ->where('status', PaymentRecordStatus::Completed->value)
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->sum('amount'),
            'revenue_month' => (int) Payment::query()
                ->where('status', PaymentRecordStatus::Completed->value)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount'),
            'payment_failures' => (int) Payment::query()->where('status', PaymentRecordStatus::Failed->value)->count(),
            'refund_requests_pending' => (int) ApprovalRequest::query()
                ->where('request_type', 'payment_refund')
                ->where('status', 'pending')
                ->count(),
            'approvals_pending' => (int) ApprovalRequest::query()->where('status', 'pending')->count(),
            'unhealthy_integrations' => (int) IntegrationConnection::query()->whereIn('status', ['failed', 'disconnected'])->count(),
            'support_sla_breaches' => $this->supportSlaBreaches(),
            'document_scan_failures' => $this->documentScanFailures(),
            'queued_job_backlog' => $this->queuedJobBacklog(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function actionQueue(): array
    {
        return [
            'unpaid_bookings' => (int) Booking::query()->where('payment_status', '!=', 'paid')->count(),
            'failed_payments' => (int) Payment::query()->where('status', PaymentRecordStatus::Failed->value)->count(),
            'cancellation_requests' => (int) ApprovalRequest::query()
                ->where('request_type', 'booking_cancellation')
                ->where('status', 'pending')
                ->count(),
            'pending_deposits' => (int) Payment::query()
                ->where('flow_type', 'deposit')
                ->whereIn('status', [PaymentRecordStatus::Pending->value, PaymentRecordStatus::Processing->value])
                ->count(),
            'unconfigured_providers' => (int) ServiceModule::query()
                ->whereIn('status', ['misconfigured', 'draft'])
                ->count(),
            'connection_test_failures' => (int) ServiceModuleHealthCheck::query()
                ->whereIn('result_status', ['failed', 'critical'])
                ->count(),
            'expiring_api_tokens' => $this->expiringApiTokens(),
            'tenant_plan_breaches' => $this->tenantPlanBreaches(),
            'pending_approvals' => (int) ApprovalRequest::query()->where('status', 'pending')->count(),
            'support_escalations' => (int) SupportTicket::query()->where('priority', 'critical')->whereIn('status', ['open', 'in_progress'])->count(),
        ];
    }

    /**
     * @return array<string, array{status: string, count: int}>
     */
    public function healthCenter(): array
    {
        return [
            'provider_connection_health' => $this->statusFromCount(
                (int) IntegrationConnection::query()->whereIn('status', ['failed', 'disconnected'])->count()
            ),
            'queue_worker_health' => $this->statusFromCount($this->failedJobsCount()),
            'document_scan_job_health' => $this->statusFromCount($this->documentScanFailures()),
            'scheduler_health' => $this->statusFromCount($this->staleSchedulerSignalCount()),
            'notification_channel_health' => $this->statusFromCount($this->notificationFailureCount()),
            'integration_request_error_rates' => $this->statusFromCount($this->integrationRequestErrorsCount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recentActivity(): array
    {
        return [
            'recent_bookings' => Booking::query()->latest('id')->limit(8)->get(['id', 'booking_number', 'status', 'total_amount', 'created_at']),
            'recent_supplier_test_failures' => ServiceModuleHealthCheck::query()
                ->whereIn('result_status', ['critical', 'failed', 'warning'])
                ->latest('id')
                ->limit(8)
                ->get(['id', 'service_module_id', 'result_status', 'message', 'checked_at']),
            'recent_exports' => ExportHistory::query()->latest('id')->limit(8)->get(['id', 'export_type', 'created_at']),
            'recent_setting_changes' => $this->recentSettingChanges(),
            'recent_admin_audit_events' => $this->recentAdminAuditEvents(),
        ];
    }

    private function supportSlaBreaches(): int
    {
        if (! Schema::hasColumn('support_tickets', 'sla_due_at')) {
            return 0;
        }

        return (int) SupportTicket::query()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereIn('status', ['open', 'in_progress'])
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

    private function queuedJobBacklog(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) \DB::table('jobs')->count();
    }

    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) \DB::table('failed_jobs')->where('failed_at', '>=', now()->copy()->subHours(24))->count();
    }

    private function staleSchedulerSignalCount(): int
    {
        if (! Schema::hasTable('job_batches')) {
            return 0;
        }

        return (int) \DB::table('job_batches')->where('created_at', '<', now()->copy()->subDay()->timestamp)->count();
    }

    private function notificationFailureCount(): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        if (! Schema::hasColumn('notifications', 'read_at')) {
            return 0;
        }

        return (int) \DB::table('notifications')->whereNull('read_at')->count() > 500 ? 1 : 0;
    }

    private function integrationRequestErrorsCount(): int
    {
        if (! Schema::hasTable('integration_logs')) {
            return 0;
        }

        if (! Schema::hasColumn('integration_logs', 'status_code')) {
            return 0;
        }

        return (int) \DB::table('integration_logs')
            ->where('created_at', '>=', now()->copy()->subHours(24))
            ->whereIn('status_code', [500, 502, 503, 504])
            ->count();
    }

    private function tenantPlanBreaches(): int
    {
        if (! Schema::hasTable('tenants')) {
            return 0;
        }
        if (! Schema::hasColumn('tenants', 'is_plan_breached')) {
            return 0;
        }

        return (int) \DB::table('tenants')->where('is_plan_breached', true)->count();
    }

    private function expiringApiTokens(): int
    {
        if (! Schema::hasTable('integration_connections')) {
            return 0;
        }

        $windowStart = CarbonImmutable::now();
        $windowEnd = $windowStart->addDay();
        $hasTokenExpiryColumn = Schema::hasColumn('integration_connections', 'token_expires_at');

        return IntegrationConnection::query()
            ->select($hasTokenExpiryColumn ? ['id', 'token_expires_at', 'config'] : ['id', 'config'])
            ->get()
            ->filter(function (IntegrationConnection $connection) use ($windowStart, $windowEnd): bool {
                if ($connection->token_expires_at !== null) {
                    return CarbonImmutable::instance($connection->token_expires_at)->betweenIncluded($windowStart, $windowEnd);
                }

                // Backward-compatible fallback: older records persist token expiry in config JSON.
                $rawExpiry = $connection->config['last_token_expires_at'] ?? null;

                if (! is_string($rawExpiry) || trim($rawExpiry) === '') {
                    return false;
                }

                try {
                    $expiry = CarbonImmutable::parse($rawExpiry);
                } catch (\Throwable) {
                    return false;
                }

                return $expiry->betweenIncluded($windowStart, $windowEnd);
            })
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentSettingChanges(): array
    {
        if (! Schema::hasTable('application_settings')) {
            return [];
        }

        return \DB::table('application_settings')
            ->select(['key', 'category', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) ($row->key ?? ''),
                'category' => (string) ($row->category ?? ''),
                'updated_at' => $row->updated_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentAdminAuditEvents(): array
    {
        if (! Schema::hasTable('compliance_audit_events')) {
            return [];
        }

        return \DB::table('compliance_audit_events')
            ->select(['event_type', 'event_action', 'created_at'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'event_type' => (string) ($row->event_type ?? ''),
                'event_action' => (string) ($row->event_action ?? ''),
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    /**
     * @return array{status: string, count: int}
     */
    private function statusFromCount(int $count): array
    {
        if ($count <= 0) {
            return ['status' => 'healthy', 'count' => 0];
        }

        return [
            'status' => $count > 10 ? 'critical' : 'warning',
            'count' => $count,
        ];
    }
}
