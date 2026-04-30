@extends('layouts.admin')

@section('title', 'Health Overview')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Health Overview</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.monitoring.integration-logs') }}" class="btn btn-sm btn-outline-secondary">Integration Logs</a>
            <a href="{{ route('admin.monitoring.async-task-monitor') }}" class="btn btn-sm btn-outline-secondary">Async Monitor</a>
            <a href="{{ route('admin.monitoring.failed-jobs-alerts') }}" class="btn btn-sm btn-outline-secondary">Failed Jobs Board</a>
        </div>
    </div>

    @php($summary = (array) ($overview['summary'] ?? []))
    <div class="row g-3 mb-3">
        @foreach($summary as $label => $value)
            <div class="col-md-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted">{{ ucwords(str_replace('_', ' ', $label)) }}</div>
                        <div class="h5 mb-0">{{ $value !== null && $value !== '' ? $value : 'N/A' }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Integration Health by Provider (24h)</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Provider</th><th>Unhealthy checks</th><th>Last failure</th></tr></thead>
                        <tbody>
                        @forelse(($overview['integration_health_by_provider'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['provider'] }}</td>
                                <td>{{ $row['unhealthy_count'] }}</td>
                                <td>{{ $row['latest_failure_at'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No unhealthy checks.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Failed Provider Tests (recent)</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Provider</th><th>Status</th><th>Checked</th><th>Message</th></tr></thead>
                        <tbody>
                        @forelse(($overview['failed_provider_tests_recent'] ?? []) as $row)
                            <tr>
                                <td>{{ $row->provider ?: ($row->provider_code ?: 'unknown') }}</td>
                                <td>{{ $row->result_status }}</td>
                                <td>{{ $row->checked_at }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit((string) $row->message, 110) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No failed provider tests.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Recent API Errors</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>At</th><th>Provider</th><th>Type</th><th>Correlation</th></tr></thead>
                        <tbody>
                        @forelse(($overview['recent_api_errors_list'] ?? []) as $row)
                            <tr>
                                <td>{{ $row->created_at ?? '-' }}</td>
                                <td>{{ $row->provider ?? '-' }}</td>
                                <td>{{ $row->log_type ?? '-' }}</td>
                                <td class="small">{{ $row->correlation_id ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No API errors in last 24 hours.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Support Escalation Breaches</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Ticket</th><th>Priority</th><th>Status</th><th>Resolution due</th></tr></thead>
                        <tbody>
                        @forelse(($overview['support_escalation_breaches_recent'] ?? []) as $row)
                            <tr>
                                <td>{{ $row->ticket_number }}</td>
                                <td>{{ $row->priority }}</td>
                                <td>{{ $row->status }}</td>
                                <td>{{ optional($row->resolution_due_at)->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No open escalation breaches.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
