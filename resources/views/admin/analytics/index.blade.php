@extends('layouts.admin')

@section('title', 'Analytics Reports')

@section('admin-content')
    @php
        $financeControls = $financeControls ?? [];
        $showNetMargin = (bool) ($financeControls['net_margin_dashboard_enabled'] ?? true);
        $marginAlertThreshold = (float) ($financeControls['net_margin_alert_threshold_percent'] ?? 8);
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">Analytics Reports</h1>
            <p class="text-muted mb-0">Dashboard, operational reports, and financial health in one place.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back to dashboard</a>
    </div>

    <form method="GET" action="{{ route('admin.analytics.index') }}" class="row g-2 mb-4">
        <div class="col-md-3">
            <label for="from_date" class="form-label">From</label>
            <input id="from_date" type="date" name="from_date" value="{{ $range['from_date'] }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="to_date" class="form-label">To</label>
            <input id="to_date" type="date" name="to_date" value="{{ $range['to_date'] }}" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-primary">Apply</button>
            <a href="{{ route('admin.analytics.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <a href="{{ route('admin.analytics.agencies-csv', $range) }}" class="btn btn-outline-success">Download Agency CSV</a>
            <a href="{{ route('admin.analytics.packages-csv', $range) }}" class="btn btn-outline-success">Download Package CSV</a>
            <form method="POST" action="{{ route('admin.analytics.queue-bundle-csv', $range) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Queue Large CSV Bundle</button>
            </form>
        </div>
    </form>
    <div class="alert alert-light border small">
        Report defaults: window {{ (int) ($financeControls['revenue_report_default_window_days'] ?? 30) }} days · include cancelled:
        {{ (bool) ($financeControls['revenue_report_include_cancelled'] ?? false) ? 'yes' : 'no' }} · net-margin widgets:
        {{ $showNetMargin ? 'enabled' : 'disabled' }}.
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Background report tasks</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>ID</th><th>Status</th><th>Requested</th><th>Finished</th><th></th></tr></thead>
                <tbody>
                @forelse($reportTasks as $task)
                    <tr>
                        <td>#{{ $task->id }}</td>
                        <td><span class="badge text-bg-light border">{{ $task->status }}</span></td>
                        <td>{{ $task->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $task->finished_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            @if($task->status === 'completed')
                                <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.analytics.task-reports.download', $task) }}">Download</a>
                            @else
                                <span class="text-muted small">{{ $task->error_message ?: 'Processing...' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No background report tasks yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Supplier retry queue tasks</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>ID</th><th>Status</th><th>Provider</th><th>Requested</th><th>Result</th></tr></thead>
                <tbody>
                @forelse($supplierRetryTasks as $task)
                    <tr>
                        <td>#{{ $task->id }}</td>
                        <td><span class="badge text-bg-light border">{{ $task->status }}</span></td>
                        <td>{{ $task->payload['provider'] ?? '—' }}</td>
                        <td>{{ $task->created_at?->format('d M Y H:i') }}</td>
                        <td class="small text-muted">
                            @if($task->status === 'completed')
                                offers: {{ $task->result['offer_count'] ?? 0 }}
                            @else
                                {{ $task->error_message ?: 'Processing...' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No supplier retry tasks yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h5 mb-3">10.1 Dashboard</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Revenue collected</div>
                    <div class="h5 mb-0">PKR {{ number_format((float) ($kpis['collected_revenue'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Gross booking value</div>
                    <div class="h5 mb-0">PKR {{ number_format((float) ($kpis['booking_gross'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Bookings</div>
                    <div class="h5 mb-0">{{ (int) ($kpis['bookings'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Confirmed bookings</div>
                    <div class="h5 mb-0">{{ (int) ($kpis['confirmed_bookings'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">10.2 Reports</h2>
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Weekly trend</strong></div>
                <div class="card-body">
                    @php $weeklyMax = max(array_map(static fn($r) => max($r['bookings'], $r['inquiries']), $trends['weekly'] ?: [['bookings' => 1, 'inquiries' => 1]])); @endphp
                    @foreach($trends['weekly'] as $row)
                        @php $bookingPct = $weeklyMax > 0 ? (($row['bookings'] / $weeklyMax) * 100) : 0; $inquiryPct = $weeklyMax > 0 ? (($row['inquiries'] / $weeklyMax) * 100) : 0; @endphp
                        <div class="mb-2 small">{{ $row['label'] }} (B: {{ $row['bookings'] }}, I: {{ $row['inquiries'] }})</div>
                        <div class="progress mb-1" style="height: 8px;"><div class="progress-bar bg-primary" style="width: {{ $bookingPct }}%"></div></div>
                        <div class="progress mb-2" style="height: 8px;"><div class="progress-bar bg-info" style="width: {{ $inquiryPct }}%"></div></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Monthly trend</strong></div>
                <div class="card-body">
                    @php $monthlyMax = max(array_map(static fn($r) => max($r['bookings'], $r['inquiries']), $trends['monthly'] ?: [['bookings' => 1, 'inquiries' => 1]])); @endphp
                    @foreach($trends['monthly'] as $row)
                        @php $bookingPct = $monthlyMax > 0 ? (($row['bookings'] / $monthlyMax) * 100) : 0; $inquiryPct = $monthlyMax > 0 ? (($row['inquiries'] / $monthlyMax) * 100) : 0; @endphp
                        <div class="mb-2 small">{{ $row['label'] }} (B: {{ $row['bookings'] }}, I: {{ $row['inquiries'] }})</div>
                        <div class="progress mb-1" style="height: 8px;"><div class="progress-bar bg-primary" style="width: {{ $bookingPct }}%"></div></div>
                        <div class="progress mb-2" style="height: 8px;"><div class="progress-bar bg-info" style="width: {{ $inquiryPct }}%"></div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong>Agency performance</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Agency</th>
                            <th class="text-end">Bookings</th>
                            <th class="text-end">Confirmed</th>
                            <th class="text-end">Value</th>
                            <th class="text-end">Collected</th>
                            @if($showNetMargin)
                                <th class="text-end">Supplier Cost</th>
                                <th class="text-end">Net Margin</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($agencyRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.bookings.index', ['agency_id' => $row['agency_id']]) }}">{{ $row['bookings_count'] }}</a>
                                </td>
                                <td class="text-end">{{ $row['confirmed_bookings_count'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['booking_value_sum'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $row['collected_revenue_sum'], 2) }}</td>
                                @if($showNetMargin)
                                    <td class="text-end">{{ number_format((float) $row['supplier_cost_sum'], 2) }}</td>
                                    <td class="text-end">
                                        {{ number_format((float) $row['net_margin_sum'], 2) }}
                                        <div class="small {{ (float) ($row['net_margin_percent'] ?? 0) < $marginAlertThreshold ? 'text-danger' : 'text-muted' }}">
                                            {{ number_format((float) $row['net_margin_percent'], 2) }}%
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showNetMargin ? 7 : 5 }}" class="text-center text-muted py-3">No agency performance data.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong>Package performance</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Package</th>
                            <th class="text-end">Inquiries</th>
                            <th class="text-end">Quoted</th>
                            <th class="text-end">Confirmed</th>
                            <th class="text-end">Potential</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($packageRows as $row)
                            <tr>
                                <td>{{ $row['title'] }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.inquiries.index', ['package_id' => $row['package_id']]) }}">{{ $row['inquiries_count'] }}</a>
                                </td>
                                <td class="text-end">{{ $row['quoted_inquiries_count'] }}</td>
                                <td class="text-end">{{ $row['confirmed_inquiries_count'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['potential_value_sum'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No package performance data.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">10.3 Financial reports</h2>
    @if($showNetMargin)
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Supplier costs</div>
                        <div class="h5 mb-0">PKR {{ number_format((float) ($financials['supplier_cost_total'] ?? 0), 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Net margin percent</div>
                        <div class="h5 mb-0">{{ number_format((float) ($financials['net_margin_percent'] ?? 0), 2) }}%</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Net margin total</div>
                        <div class="h5 mb-0">PKR {{ number_format((float) ($financials['net_margin_total'] ?? 0), 2) }}</div>
                        @if((float) ($financials['net_margin_percent'] ?? 0) < $marginAlertThreshold)
                            <div class="small text-danger mt-1">Below configured threshold ({{ number_format($marginAlertThreshold, 2) }}%).</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong>Payment aging (outstanding)</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>Bucket</th>
                    <th class="text-end">Outstanding amount</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>0-30 days</td>
                    <td class="text-end">{{ number_format((float) ($paymentAging['current_0_30'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>31-60 days</td>
                    <td class="text-end">{{ number_format((float) ($paymentAging['due_31_60'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>61-90 days</td>
                    <td class="text-end">{{ number_format((float) ($paymentAging['due_61_90'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>90+ days</td>
                    <td class="text-end">{{ number_format((float) ($paymentAging['due_90_plus'] ?? 0), 2) }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
