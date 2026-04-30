@extends('layouts.admin')

@section('title', 'Compliance & Safety')

@section('admin-content')
    <h1 class="h4 mb-3">Compliance & Safety</h1>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">API calls (24h)</div><div class="h5 mb-0">{{ $apiMonitoring['total_calls'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">API failures (24h)</div><div class="h5 mb-0">{{ $apiMonitoring['failed_calls'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Error rate</div><div class="h5 mb-0">{{ number_format((float) $apiMonitoring['error_rate_percent'], 2) }}%</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Pending approvals</div><div class="h5 mb-0">{{ $pendingApprovals->count() }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Submit approval request</strong></div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger py-2">
                    <ul class="mb-0 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.compliance.approvals.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="request_type" required>
                        <option value="">Select high-risk action...</option>
                        @foreach(($supportedApprovalTypes ?? []) as $type)
                            <option value="{{ $type }}" @selected(old('request_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="reference_type" value="{{ old('reference_type') }}" placeholder="reference_type (payment/booking/tenant/module/integration/agency)"></div>
                <div class="col-md-2"><input class="form-control form-control-sm" name="reference_id" type="number" min="1" value="{{ old('reference_id') }}" placeholder="reference_id"></div>
                <div class="col-md-4"><input class="form-control form-control-sm" name="reason" value="{{ old('reason') }}" placeholder="Reason"></div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" placeholder="Expiry (optional)"></div>
                <div class="col-md-6"><input class="form-control form-control-sm" name="payload[reference_url]" value="{{ old('payload.reference_url') }}" placeholder="Optional object link URL"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary">Submit</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Approval queue</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Reference</th><th>TTL</th><th>Reason</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($pendingApprovals as $approval)
                    <tr>
                        <td>#{{ $approval->id }}</td>
                        <td>{{ $approval->request_type }}</td>
                        <td>{{ $approval->status }}</td>
                        <td>
                            @if($approval->reference_url)
                                <a href="{{ $approval->reference_url }}" target="_blank" rel="noopener">Open</a>
                            @elseif($approval->reference_type || $approval->reference_id)
                                <span class="small text-muted">{{ $approval->reference_type }} #{{ $approval->reference_id }}</span>
                            @else
                                <span class="small text-muted">-</span>
                            @endif
                        </td>
                        <td class="small">{{ optional($approval->expires_at)->format('d M Y H:i') ?: '-' }}</td>
                        <td>{{ $approval->reason }}</td>
                        <td>{{ optional($approval->submitted_at)->format('d M Y H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.compliance.approvals.review', $approval) }}">
                                @csrf
                                <div class="d-flex gap-1">
                                    <input type="hidden" name="decision" value="approve">
                                    <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Approval note">
                                    <button class="btn btn-sm btn-outline-success">Approve</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.compliance.approvals.review', $approval) }}" class="mt-1">
                                @csrf
                                <div class="d-flex gap-1">
                                    <input type="hidden" name="decision" value="reject">
                                    <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Rejection note">
                                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No pending approvals.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Approval history</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Reference</th><th>Reviewed</th><th>Note</th><th>Consumed</th></tr></thead>
                <tbody>
                @forelse(($approvalHistory ?? []) as $approval)
                    <tr>
                        <td>#{{ $approval->id }}</td>
                        <td>{{ $approval->request_type }}</td>
                        <td>{{ $approval->status }}</td>
                        <td>
                            @if($approval->reference_url)
                                <a href="{{ $approval->reference_url }}" target="_blank" rel="noopener">Open</a>
                            @else
                                <span class="small text-muted">{{ $approval->reference_type ?? '-' }}{{ $approval->reference_id ? ' #'.$approval->reference_id : '' }}</span>
                            @endif
                        </td>
                        <td>{{ optional($approval->reviewed_at)->format('d M Y H:i') ?: '-' }}</td>
                        <td class="small">{{ $approval->review_note ?: '-' }}</td>
                        <td>{{ optional($approval->consumed_at)->format('d M Y H:i') ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No approval history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>API monitoring by provider (24h)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Provider</th><th>Total</th><th>Failed</th></tr></thead>
                <tbody>
                @forelse($apiMonitoring['by_provider'] as $row)
                    <tr><td>{{ $row['provider'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['failed'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">No integration logs in selected window.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Backup runs</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Status</th><th>Type</th><th>Path</th><th>Created</th></tr></thead>
                <tbody>
                @forelse($backupRuns as $run)
                    <tr><td>#{{ $run->id }}</td><td>{{ $run->status }}</td><td>{{ $run->backup_type }}</td><td class="small">{{ $run->path }}</td><td>{{ $run->created_at?->format('d M Y H:i') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No backup runs logged yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Recent audit logs</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>When</th><th>Area</th><th>Action</th><th>Severity</th><th>User</th></tr></thead>
                <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        <td>{{ $log->area }}</td>
                        <td class="small">{{ $log->action }}</td>
                        <td>{{ $log->severity }}</td>
                        <td>{{ $log->actor?->name ?? 'system' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No audit events yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
