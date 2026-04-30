<?php

namespace App\Services\Analytics;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentSource;
use App\Models\Agency;
use App\Models\ApprovalRequest;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\ExportHistory;
use App\Models\Inquiry;
use App\Models\IntegrationConnection;
use App\Models\IntegrationLog;
use App\Models\Payment;
use App\Models\PaymentGatewayTransaction;
use App\Models\Refund;
use App\Models\ServiceModule;
use App\Models\ServiceModuleHealthCheck;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TravelPackage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class AdminAnalyticsService
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    /**
     * @return array<string, int|float>
     */
    public function bookingStats(): array
    {
        return [
            'total_bookings' => (int) Booking::query()->count(),
            'this_month_bookings' => (int) Booking::query()
                ->whereBetween('created_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
                ->count(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function paymentStats(): array
    {
        return [
            'failed_payments' => (int) Payment::query()->where('status', PaymentRecordStatus::Failed->value)->count(),
            'pending_deposits' => (int) Payment::query()
                ->where('flow_type', 'deposit')
                ->whereIn('status', [PaymentRecordStatus::Pending->value, PaymentRecordStatus::Processing->value])
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function supportStats(): array
    {
        return [
            'pending_support_tickets' => (int) SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function integrationHealthStats(): array
    {
        return [
            'total_unhealthy_connections' => (int) IntegrationConnection::query()->whereIn('status', ['failed', 'disconnected'])->count(),
            'pending_integration_tests_failed' => (int) IntegrationConnection::query()->where('last_tested_status', 'failed')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function moduleActivationStats(): array
    {
        return [
            'total_active_modules' => (int) ServiceModule::query()->where('is_active', true)->count(),
            'total_configured_providers' => (int) ServiceModule::query()
                ->where('status', '!=', 'misconfigured')
                ->where('connection_status', '!=', 'disconnected')
                ->count(),
            'total_unhealthy_modules' => (int) ServiceModule::query()
                ->whereIn('connection_status', ['disconnected', 'failed', 'warning'])
                ->count(),
        ];
    }

    public function pendingApprovalsCount(): int
    {
        return (int) ApprovalRequest::query()->where('status', 'pending')->count();
    }

    /**
     * @return array{from_date: string, to_date: string}
     */
    public function resolveDateRange(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate !== null && $fromDate !== ''
            ? Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $to = $toDate !== null && $toDate !== ''
            ? Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay()
            : now()->endOfDay();

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardKpis(string $fromDate, string $toDate): array
    {
        $bookings = Booking::query()
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']);

        $bookingCount = (clone $bookings)->count();
        $confirmedCount = (clone $bookings)->where('status', 'confirmed')->count();
        $bookingGross = (float) ((clone $bookings)->sum('total_amount') ?: 0.0);

        $collectedRevenue = (float) Payment::query()
            ->where('status', PaymentRecordStatus::Completed->value)
            ->where('source', '!=', PaymentSource::AgencyWallet->value)
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('amount');

        return [
            'bookings' => $bookingCount,
            'confirmed_bookings' => $confirmedCount,
            'booking_gross' => $bookingGross,
            'collected_revenue' => $collectedRevenue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardOverview(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $thisMonthBookings = (int) Booking::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();
        $lastMonthBookings = (int) Booking::query()
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $thisMonthRevenue = (float) Payment::query()
            ->where('status', PaymentRecordStatus::Completed->value)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $lastMonthRevenue = (float) Payment::query()
            ->where('status', PaymentRecordStatus::Completed->value)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $bookingStats = $this->bookingStats();
        $paymentStats = $this->paymentStats();
        $supportStats = $this->supportStats();
        $integrationStats = $this->integrationHealthStats();
        $moduleStats = $this->moduleActivationStats();
        $pendingApprovals = $this->pendingApprovalsCount();

        $kpi = [
            'total_bookings' => (int) $bookingStats['total_bookings'],
            'total_users' => (int) User::query()->count(),
            'this_month_bookings' => (int) $bookingStats['this_month_bookings'],
            'this_month_revenue' => $thisMonthRevenue,
            'pending_refunds' => (int) Refund::query()->where('status', 'pending')->count(),
            'pending_deposits' => (int) $paymentStats['pending_deposits'],
            'failed_payments' => (int) $paymentStats['failed_payments'],
            'pending_support_tickets' => (int) $supportStats['pending_support_tickets'],
        ];

        $actionQueue = [
            'unpaid_bookings' => (int) Booking::query()->where('payment_status', '!=', 'paid')->count(),
            'failed_payments' => (int) $paymentStats['failed_payments'],
            'cancellation_requests' => (int) ApprovalRequest::query()
                ->where('request_type', 'booking_cancellation')
                ->where('status', 'pending')
                ->count(),
            'pending_deposits' => $kpi['pending_deposits'],
            'inactive_users' => (int) User::query()->whereNull('email_verified_at')->count(),
            'expiring_quotations' => (int) \App\Models\Quotation::query()
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
                ->count(),
            'integration_connection_failures' => (int) IntegrationConnection::query()->where('status', 'failed')->count(),
            'document_scan_failures' => $this->countDocumentScanFailures(),
            'approval_requests_pending' => $pendingApprovals,
        ];

        return [
            'kpi' => $kpi,
            'performance' => [
                'bookings_current_month' => $thisMonthBookings,
                'bookings_last_month' => $lastMonthBookings,
                'revenue_current_month' => $thisMonthRevenue,
                'revenue_last_month' => $lastMonthRevenue,
                'booking_count_by_module' => [
                    'quotation' => (int) Booking::query()->whereNotNull('quotation_id')->count(),
                    'flight' => (int) Booking::query()->whereNotNull('supplier_flight_hook_status')->count(),
                    'hotel' => (int) Booking::query()->whereNotNull('supplier_hotel_hook_status')->count(),
                ],
                'top_providers' => IntegrationLog::query()
                    ->selectRaw('provider, COUNT(*) as total')
                    ->whereNotNull('provider')
                    ->groupBy('provider')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
                    ->map(fn ($r): array => ['provider' => (string) $r->provider, 'total' => (int) $r->total])
                    ->all(),
                'payment_aging_snapshot' => $this->paymentAging($now->toDateString()),
                'top_tenants' => Tenant::query()
                    ->withCount('agencies')
                    ->orderByDesc('agencies_count')
                    ->limit(5)
                    ->get(['id', 'name'])
                    ->map(fn (Tenant $t): array => ['name' => $t->name, 'agencies_count' => (int) $t->agencies_count])
                    ->all(),
                'top_agencies' => Agency::query()
                    ->withCount('bookings')
                    ->orderByDesc('bookings_count')
                    ->limit(5)
                    ->get(['id', 'name'])
                    ->map(fn (Agency $a): array => ['name' => $a->name, 'bookings_count' => (int) $a->bookings_count])
                    ->all(),
            ],
            'action_queue' => $actionQueue,
            'live_management' => [
                'total_active_modules' => (int) $moduleStats['total_active_modules'],
                'total_configured_providers' => (int) $moduleStats['total_configured_providers'],
                'total_unhealthy_connections' => (int) $integrationStats['total_unhealthy_connections'],
                'total_unhealthy_modules' => (int) $moduleStats['total_unhealthy_modules'],
                'total_pending_integration_tests_failed' => (int) $integrationStats['pending_integration_tests_failed'],
                'pending_approvals_count' => $pendingApprovals,
            ],
            'recent' => [
                'bookings' => Booking::query()->latest('id')->limit(8)->get(['id', 'booking_number', 'status', 'total_amount', 'created_at']),
                'support_tickets' => SupportTicket::query()->latest('id')->limit(8)->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'created_at']),
                'failed_scans' => $this->hasTable('booking_documents')
                    ? BookingDocument::query()
                        ->whereIn('virus_scan_status', [BookingDocument::SCAN_FAILED, BookingDocument::SCAN_INFECTED, BookingDocument::SCAN_SUSPICIOUS])
                        ->latest('id')
                        ->limit(8)
                        ->get(['id', 'booking_id', 'document_type', 'virus_scan_status', 'created_at'])
                    : collect(),
                'integration_test_failures' => ServiceModuleHealthCheck::query()
                    ->whereIn('result_status', ['critical', 'failed', 'warning'])
                    ->orderByDesc('checked_at')
                    ->limit(8)
                    ->get(['id', 'service_module_id', 'result_status', 'message', 'checked_at']),
                'exports' => ExportHistory::query()->latest('id')->limit(8)->get(['id', 'user_id', 'export_type', 'created_at']),
                'failed_transactions' => PaymentGatewayTransaction::query()
                    ->where('status', 'failed')
                    ->latest('id')
                    ->limit(8)
                    ->get(['id', 'gateway_driver', 'external_id', 'amount', 'currency', 'created_at']),
            ],
        ];
    }

    /**
     * @return array{
     *   agency_rows: list<array<string, mixed>>,
     *   package_rows: list<array<string, mixed>>,
     *   financials: array<string, mixed>,
     *   payment_aging: array<string, float>
     * }
     */
    public function reports(string $fromDate, string $toDate): array
    {
        $agencyRows = $this->agencyReportRows($fromDate, $toDate);
        $packageRows = $this->packageReportRows($fromDate, $toDate);
        $financials = $this->financialMetrics($fromDate, $toDate);
        $aging = $this->paymentAging($toDate);

        return [
            'agency_rows' => $agencyRows,
            'package_rows' => $packageRows,
            'financials' => $financials,
            'payment_aging' => $aging,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function agencyReportRows(string $fromDate, string $toDate): array
    {
        $agencyRows = Agency::query()
            ->select('agencies.id', 'agencies.name')
            ->withCount([
                'bookings as bookings_count' => fn (Builder $q): Builder => $q->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
                'bookings as confirmed_bookings_count' => fn (Builder $q): Builder => $q
                    ->where('status', 'confirmed')
                    ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
            ])
            ->withSum([
                'bookings as booking_value_sum' => fn (Builder $q): Builder => $q->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
            ], 'total_amount')
            ->orderByDesc('booking_value_sum')
            ->limit(10)
            ->get()
            ->keyBy('id');

        $collectedByAgency = Payment::query()
            ->selectRaw('agency_id, COALESCE(SUM(amount), 0) as total')
            ->where('status', PaymentRecordStatus::Completed->value)
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->groupBy('agency_id')
            ->pluck('total', 'agency_id');

        $agencyRows = $agencyRows
            ->map(fn (Agency $agency): array => [
                'agency_id' => $agency->id,
                'name' => $agency->name,
                'bookings_count' => (int) ($agency->bookings_count ?? 0),
                'confirmed_bookings_count' => (int) ($agency->confirmed_bookings_count ?? 0),
                'booking_value_sum' => (float) ($agency->booking_value_sum ?? 0),
                'collected_revenue_sum' => (float) ($collectedByAgency[$agency->id] ?? 0),
                'supplier_cost_sum' => (float) Booking::query()
                    ->where('agency_id', $agency->id)
                    ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
                    ->sum('supplier_cost_total'),
            ])
            ->map(static function (array $row): array {
                $net = round(((float) $row['booking_value_sum']) - ((float) $row['supplier_cost_sum']), 2);
                $row['net_margin_sum'] = $net;
                $row['net_margin_percent'] = ((float) $row['booking_value_sum']) > 0
                    ? round(($net / (float) $row['booking_value_sum']) * 100, 2)
                    : 0.0;

                return $row;
            })
            ->values()
            ->all();

        return $agencyRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packageReportRows(string $fromDate, string $toDate): array
    {
        return TravelPackage::query()
            ->select('packages.id', 'packages.title')
            ->withCount([
                'inquiries as inquiries_count' => fn (Builder $q): Builder => $q->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
                'inquiries as confirmed_inquiries_count' => fn (Builder $q): Builder => $q
                    ->where('status', Inquiry::STATUS_CONFIRMED)
                    ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
                'inquiries as quoted_inquiries_count' => fn (Builder $q): Builder => $q
                    ->whereIn('status', [Inquiry::STATUS_QUOTED, Inquiry::STATUS_REVISION_REQUESTED, Inquiry::STATUS_CONFIRMED])
                    ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
            ])
            ->withSum([
                'inquiries as potential_value_sum' => fn (Builder $q): Builder => $q->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59']),
            ], 'budget')
            ->orderByDesc('inquiries_count')
            ->limit(10)
            ->get()
            ->map(static fn (TravelPackage $package): array => [
                'package_id' => $package->id,
                'title' => $package->title,
                'inquiries_count' => (int) ($package->inquiries_count ?? 0),
                'quoted_inquiries_count' => (int) ($package->quoted_inquiries_count ?? 0),
                'confirmed_inquiries_count' => (int) ($package->confirmed_inquiries_count ?? 0),
                'potential_value_sum' => (float) ($package->potential_value_sum ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   weekly: list<array{label: string, bookings: int, inquiries: int, collected_revenue: float}>,
     *   monthly: list<array{label: string, bookings: int, inquiries: int, collected_revenue: float}>
     * }
     */
    public function trends(string $fromDate, string $toDate): array
    {
        return [
            'weekly' => $this->buildTrendRows($fromDate, $toDate, 'week'),
            'monthly' => $this->buildTrendRows($fromDate, $toDate, 'month'),
        ];
    }

    /**
     * @return list<array{label: string, bookings: int, inquiries: int, collected_revenue: float}>
     */
    private function buildTrendRows(string $fromDate, string $toDate, string $bucket): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        $rows = [];
        $cursor = $bucket === 'week' ? $from->copy()->startOfWeek() : $from->copy()->startOfMonth();

        while ($cursor->lte($to)) {
            $start = $cursor->copy();
            $end = $bucket === 'week'
                ? $cursor->copy()->endOfWeek()->min($to)
                : $cursor->copy()->endOfMonth()->min($to);

            $bookings = (int) Booking::query()
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->count();
            $inquiries = (int) Inquiry::query()
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->count();
            $collected = (float) Payment::query()
                ->where('status', PaymentRecordStatus::Completed->value)
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->sum('amount');

            $rows[] = [
                'label' => $bucket === 'week'
                    ? $start->format('d M').' - '.$end->format('d M')
                    : $start->format('M Y'),
                'bookings' => $bookings,
                'inquiries' => $inquiries,
                'collected_revenue' => $collected,
            ];

            $cursor = $bucket === 'week' ? $cursor->addWeek() : $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    private function financialMetrics(string $fromDate, string $toDate): array
    {
        $grossBookingValue = (float) Booking::query()
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('total_amount');

        $collectedRevenue = (float) Payment::query()
            ->where('status', PaymentRecordStatus::Completed->value)
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('amount');

        $refunds = (float) Refund::query()
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('amount');

        $supplierCosts = (float) Booking::query()
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('supplier_cost_total');
        $netMargin = round($grossBookingValue - $supplierCosts, 2);
        $profitMarginPct = $grossBookingValue > 0
            ? round(($netMargin / $grossBookingValue) * 100, 2)
            : 0.0;

        return [
            'gross_booking_value' => $grossBookingValue,
            'collected_revenue' => $collectedRevenue,
            'refunds_estimate' => $refunds,
            'supplier_cost_total' => $supplierCosts,
            'net_margin_total' => $netMargin,
            'net_margin_percent' => $profitMarginPct,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function paymentAging(string $asOfDate): array
    {
        $asOf = Carbon::createFromFormat('Y-m-d', $asOfDate)->endOfDay();

        $completedByBooking = Payment::query()
            ->selectRaw('booking_id, COALESCE(SUM(amount), 0) as paid_total')
            ->whereNotNull('booking_id')
            ->where('status', PaymentRecordStatus::Completed->value)
            ->groupBy('booking_id')
            ->get()
            ->keyBy('booking_id');

        $buckets = [
            'current_0_30' => 0.0,
            'due_31_60' => 0.0,
            'due_61_90' => 0.0,
            'due_90_plus' => 0.0,
        ];

        Booking::query()
            ->whereIn('status', ['draft', 'on_hold', 'confirmed'])
            ->whereNotNull('booked_at')
            ->chunkById(200, function (Collection $bookings) use (&$buckets, $completedByBooking, $asOf): void {
                foreach ($bookings as $booking) {
                    $total = (float) ($booking->total_amount ?? 0);
                    $paid = (float) (($completedByBooking->get($booking->id)->paid_total ?? 0));
                    $outstanding = round(max($total - $paid, 0), 2);
                    if ($outstanding <= 0) {
                        continue;
                    }

                    $age = Carbon::parse($booking->booked_at)->diffInDays($asOf, false);
                    if ($age <= 30) {
                        $buckets['current_0_30'] += $outstanding;
                    } elseif ($age <= 60) {
                        $buckets['due_31_60'] += $outstanding;
                    } elseif ($age <= 90) {
                        $buckets['due_61_90'] += $outstanding;
                    } else {
                        $buckets['due_90_plus'] += $outstanding;
                    }
                }
            });

        return $buckets;
    }

    private function countDocumentScanFailures(): int
    {
        if (! $this->hasTable('booking_documents')) {
            return 0;
        }

        return (int) BookingDocument::query()
            ->whereIn('virus_scan_status', [
                BookingDocument::SCAN_FAILED,
                BookingDocument::SCAN_INFECTED,
                BookingDocument::SCAN_SUSPICIOUS,
            ])
            ->count();
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        return $this->tableExistsCache[$table] = Schema::hasTable($table);
    }
}
