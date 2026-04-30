@extends('layouts.admin')

@section('title', 'Failed Jobs & Alerts')

@section('admin-content')
    <h1 class="h4 mb-3">Failed Jobs / Alerts Board</h1>
    <div class="alert alert-light border mb-3 py-2">
        <strong>Scheduler last run:</strong> {{ $board['scheduler_last_run'] ?? 'N/A' }}
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Failed Queue Jobs</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Queue</th><th>Failed At</th><th>Error</th></tr></thead>
                <tbody>
                @forelse(($board['failed_jobs'] ?? []) as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->queue ?? '-' }}</td>
                        <td>{{ $row->failed_at ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit((string) ($row->exception ?? ''), 180) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No failed jobs.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Failed Provider Tests</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Module</th><th>Status</th><th>Checked</th><th>Message</th></tr></thead>
                <tbody>
                @forelse(($board['failed_provider_tests'] ?? []) as $row)
                    <tr>
                        <td>#{{ $row->service_module_id }}</td>
                        <td>{{ $row->result_status }}</td>
                        <td>{{ $row->checked_at }}</td>
                        <td class="small">{{ $row->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No failed provider tests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Payment Gateway Failures</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Gateway</th><th>External Ref</th><th>Amount</th><th>At</th></tr></thead>
                <tbody>
                @forelse(($board['payment_gateway_failures'] ?? []) as $row)
                    <tr>
                        <td>{{ $row->gateway_driver }}</td>
                        <td>{{ $row->external_id }}</td>
                        <td>{{ $row->currency }} {{ number_format((float) $row->amount, 2) }}</td>
                        <td>{{ $row->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No payment gateway failures.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Notification Failures (from failed jobs)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Queue</th><th>Failed At</th><th>Error</th></tr></thead>
                <tbody>
                @forelse(($board['notification_failures'] ?? []) as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->queue ?? '-' }}</td>
                        <td>{{ $row->failed_at ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit((string) ($row->exception ?? ''), 180) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No notification failures detected.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Document Scan Failures</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Document</th><th>Booking</th><th>Status</th><th>Scanned At</th><th>Note</th></tr></thead>
                <tbody>
                @forelse(($board['document_scan_failures'] ?? []) as $row)
                    <tr>
                        <td>#{{ $row->id }}</td>
                        <td>#{{ $row->booking_id }}</td>
                        <td>{{ $row->virus_scan_status }}</td>
                        <td>{{ optional($row->virus_scanned_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit((string) ($row->virus_scan_note ?? ''), 120) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No document scan failures.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Support Escalation Breaches</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Ticket</th><th>Priority</th><th>Status</th><th>Resolution Due</th></tr></thead>
                <tbody>
                @forelse(($board['support_escalation_breaches'] ?? []) as $row)
                    <tr>
                        <td>{{ $row->ticket_number }}</td>
                        <td>{{ $row->priority }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ optional($row->resolution_due_at)->format('d M Y H:i') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No active support escalation breaches.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
