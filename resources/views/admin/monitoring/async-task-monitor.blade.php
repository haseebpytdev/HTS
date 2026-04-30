@extends('layouts.admin')

@section('title', 'Async Task Monitor')

@section('admin-content')
    <h1 class="h4 mb-3">Async Task Monitor</h1>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Jobs Backlog</div><div class="h5 mb-0">{{ (int) ($async['jobs_backlog'] ?? 0) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Failed Jobs (24h)</div><div class="h5 mb-0">{{ (int) ($async['failed_jobs_24h'] ?? 0) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Stale Batches</div><div class="h5 mb-0">{{ (int) ($async['job_batches_stale'] ?? 0) }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Scheduler Last Run</div><div class="h6 mb-0">{{ $async['scheduler_last_run'] ?? 'N/A' }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Recent Queue Failures</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>ID</th><th>Queue</th><th>Failed at</th><th>Error</th></tr></thead>
                        <tbody>
                        @forelse(($async['failed_jobs_recent'] ?? []) as $row)
                            <tr>
                                <td>{{ $row->id }}</td>
                                <td>{{ $row->queue ?? '-' }}</td>
                                <td>{{ $row->failed_at ?? '-' }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit((string) ($row->exception ?? ''), 120) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No queue failures logged.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Document Scan Task Failures</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Task</th><th>Reference</th><th>Error</th></tr></thead>
                        <tbody>
                        @forelse(($async['document_scan_task_failures'] ?? []) as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td class="small">{{ $row->reference_type }} #{{ $row->reference_id }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit((string) ($row->error_message ?? ''), 90) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No failed document scan tasks.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
