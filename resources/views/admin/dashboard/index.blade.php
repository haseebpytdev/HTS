@extends('layouts.admin')

@section('title', 'Dashboard')

@section('admin-content')
    @php($vis = $widgetVisibility ?? [])
    @php($kpi = $executiveKpis ?? [])
    @php($queue = $actionQueue ?? [])
    @php($health = $healthCenter ?? [])
    @php($recent = $recentActivity ?? [])

    <header class="mb-4">
        <h1 class="admin-dash-page-title">Dashboard</h1>
        <p class="text-muted mb-0 small">Live overview of bookings, revenue, risk signals, and operational health.</p>
    </header>

    <div class="admin-dash-actions-panel shadow-sm mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="fw-semibold text-dark">Operations</div>
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end flex-grow-1">
                @if(auth()->user()?->role === \App\Enums\UserRole::SUPER_ADMIN)
                    <a href="{{ route('admin.integrations.index') }}" class="btn btn-sm btn-primary">Integration hub</a>
                    <a href="{{ route('admin.integrations.accounts.index') }}" class="btn btn-sm btn-outline-secondary">API credentials</a>
                @endif
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary">Bookings</a>
                <a href="{{ route('admin.quotations.index') }}" class="btn btn-sm btn-outline-secondary">Quotations</a>
                @if(\Illuminate\Support\Facades\Route::has('admin.analytics.index'))
                    <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-secondary">Analytics</a>
                @endif
                <a href="{{ route('frontend.home') }}" class="btn btn-sm btn-outline-primary">Public site</a>
            </div>
        </div>
    </div>

    @if(($vis['show_executive_kpis'] ?? true))
        @php($metricRows = [
            [
                ['key' => 'bookings_today', 'label' => 'Bookings today', 'icon' => 'bi-calendar-day', 'format' => 'int'],
                ['key' => 'bookings_month', 'label' => 'Bookings this month', 'icon' => 'bi-calendar3', 'format' => 'int'],
                ['key' => 'revenue_today', 'label' => 'Revenue today', 'icon' => 'bi-currency-exchange', 'format' => 'pkr'],
                ['key' => 'revenue_month', 'label' => 'Revenue this month', 'icon' => 'bi-graph-up', 'format' => 'pkr'],
            ],
            [
                ['key' => 'payment_failures', 'label' => 'Payment failures', 'icon' => 'bi-exclamation-triangle', 'format' => 'int', 'danger' => true],
                ['key' => 'refund_requests_pending', 'label' => 'Refunds pending', 'icon' => 'bi-arrow-counterclockwise', 'format' => 'int'],
                ['key' => 'approvals_pending', 'label' => 'Approvals pending', 'icon' => 'bi-hourglass-split', 'format' => 'int'],
                ['key' => 'unhealthy_integrations', 'label' => 'Unhealthy integrations', 'icon' => 'bi-plug', 'format' => 'int', 'danger' => true],
            ],
        ])
        @foreach($metricRows as $row)
            <div class="row g-3 mb-3">
                @foreach($row as $m)
                    @php($val = (int) ($kpi[$m['key']] ?? 0))
                    <div class="col-6 col-xl-3">
                        <div class="card admin-dash-metric-card shadow-sm h-100">
                            <div class="card-body d-flex gap-3 align-items-start">
                                <div class="admin-dash-metric-card__icon">
                                    <i class="bi {{ $m['icon'] }}"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="admin-dash-metric-card__label">{{ $m['label'] }}</div>
                                    <div class="admin-dash-metric-card__value {{ !empty($m['danger']) && $val > 0 ? 'text-danger' : '' }}">
                                        @if(($m['format'] ?? '') === 'pkr')
                                            PKR {{ number_format($val, 0) }}
                                        @else
                                            {{ number_format($val) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card admin-dash-metric-card shadow-sm h-100">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <div class="admin-dash-metric-card__icon"><i class="bi bi-headset"></i></div>
                        <div>
                            <div class="admin-dash-metric-card__label">Support SLA breaches</div>
                            <div class="admin-dash-metric-card__value text-warning">{{ number_format((int) ($kpi['support_sla_breaches'] ?? 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card admin-dash-metric-card shadow-sm h-100">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <div class="admin-dash-metric-card__icon"><i class="bi bi-file-earmark-medical"></i></div>
                        <div>
                            <div class="admin-dash-metric-card__label">Document scan failures</div>
                            <div class="admin-dash-metric-card__value text-danger">{{ number_format((int) ($kpi['document_scan_failures'] ?? 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card admin-dash-metric-card shadow-sm h-100">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <div class="admin-dash-metric-card__icon"><i class="bi bi-lightning-charge"></i></div>
                        <div>
                            <div class="admin-dash-metric-card__label">Queued job backlog</div>
                            <div class="admin-dash-metric-card__value">{{ number_format((int) ($kpi['queued_job_backlog'] ?? 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge bg-primary">Internal only</span>
                    <span class="small text-muted fw-semibold">Operations snapshot</span>
                </div>
                <div class="row g-3 text-center small">
                    <div class="col-6 col-md-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.06em;">Unpaid bookings</div>
                        <div class="fs-5 fw-bold text-dark">{{ number_format((int) ($queue['unpaid_bookings'] ?? 0)) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.06em;">Failed payments</div>
                        <div class="fs-5 fw-bold text-danger">{{ number_format((int) ($queue['failed_payments'] ?? 0)) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.06em;">Pending deposits</div>
                        <div class="fs-5 fw-bold text-dark">{{ number_format((int) ($queue['pending_deposits'] ?? 0)) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.06em;">Pending approvals</div>
                        <div class="fs-5 fw-bold text-dark">{{ number_format((int) ($queue['pending_approvals'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @if(($vis['show_action_queue'] ?? true))
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Action queue</h2>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Unpaid bookings</span><span class="badge bg-warning text-dark">{{ (int) ($queue['unpaid_bookings'] ?? 0) }}</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Failed payments</span><span class="badge bg-danger">{{ (int) ($queue['failed_payments'] ?? 0) }}</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Cancellation requests</span><span class="badge bg-secondary">{{ (int) ($queue['cancellation_requests'] ?? 0) }}</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Unconfigured providers</span><span class="badge bg-warning text-dark">{{ (int) ($queue['unconfigured_providers'] ?? 0) }}</span></li>
                            <li class="list-group-item d-flex justify-content-between px-0"><span>Support escalations</span><span class="badge bg-danger">{{ (int) ($queue['support_escalations'] ?? 0) }}</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if(($vis['show_health_center'] ?? true))
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Health center</h2>
                        @foreach(($health ?? []) as $label => $state)
                            @php($tone = ($state['status'] ?? 'healthy') === 'critical' ? 'danger' : (($state['status'] ?? 'healthy') === 'warning' ? 'warning' : 'success'))
                            <div class="small d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>{{ ucwords(str_replace('_', ' ', $label)) }}</span>
                                <span class="badge bg-{{ $tone }}">{{ strtoupper((string) ($state['status'] ?? 'healthy')) }} ({{ (int) ($state['count'] ?? 0) }})</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">Recent bookings</h2>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 as-table">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Status</th>
                            <th class="text-end">Amount (PKR)</th>
                            <th class="text-end">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($recent['recent_bookings'] ?? []) as $row)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('admin.bookings.show', $row) }}" class="text-decoration-none">{{ $row->booking_number ?? ('#'.$row->id) }}</a>
                                </td>
                                <td><span class="badge rounded-pill bg-light text-dark border">{{ $row->status }}</span></td>
                                <td class="text-end">{{ number_format((float) $row->total_amount, 0) }}</td>
                                <td class="text-end text-muted small">{{ $row->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No recent bookings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(($vis['show_recent_activity'] ?? true))
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Supplier test failures</h2>
                        @forelse(($recent['recent_supplier_test_failures'] ?? []) as $row)
                            <div class="small border-bottom py-2">Module #{{ $row->service_module_id }} · {{ strtoupper((string) $row->result_status) }}</div>
                        @empty
                            <x-ui.empty-state class="p-3" title="No failures" message="Healthy provider checks." />
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Recent exports</h2>
                        @forelse(($recent['recent_exports'] ?? []) as $row)
                            <div class="small border-bottom py-2">{{ $row->export_type }} · {{ $row->created_at?->diffForHumans() }}</div>
                        @empty
                            <div class="small text-muted">No recent exports.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(($vis['show_quick_controls'] ?? true) && count($quickActions ?? []) > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Quick controls</h2>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(($quickActions ?? []) as $action)
                        <a href="{{ $action['route'] }}" class="btn btn-sm btn-{{ $action['tone'] }}">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
