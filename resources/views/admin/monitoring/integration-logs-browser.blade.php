@extends('layouts.admin')

@section('title', 'Integration Logs Browser')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Integration Logs Browser</h1>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="provider" class="form-control form-control-sm" placeholder="provider (amadeus_self_service/sabre/travelport/iati/duffel)" value="{{ $provider }}">
            <button class="btn btn-sm btn-outline-primary">Filter</button>
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>At</th>
                    <th>Provider</th>
                    <th>Type</th>
                    <th>HTTP</th>
                    <th>Latency</th>
                    <th>Correlation</th>
                    <th>Payload</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $row)
                    @php
                        $statusCode = null;
                        $latencyMs = null;
                        $payload = is_array($row->payload ?? null) ? $row->payload : [];
                        if (isset($payload['status_code'])) {
                            $statusCode = $payload['status_code'];
                        } elseif (isset($payload['http_status'])) {
                            $statusCode = $payload['http_status'];
                        }
                        if (isset($payload['latency_ms'])) {
                            $latencyMs = $payload['latency_ms'];
                        } elseif (isset($payload['duration_ms'])) {
                            $latencyMs = $payload['duration_ms'];
                        }
                    @endphp
                    <tr>
                        <td>{{ $row->created_at?->format('d M Y H:i:s') }}</td>
                        <td>{{ $row->provider }}</td>
                        <td>{{ $row->log_type }}</td>
                        <td>{{ $statusCode ?? '-' }}</td>
                        <td>{{ $latencyMs !== null ? $latencyMs.' ms' : '-' }}</td>
                        <td class="small">{{ $row->correlation_id }}</td>
                        <td class="small">
                            @if($canViewSensitivePayload)
                                <code>{{ \Illuminate\Support\Str::limit(json_encode($row->payload), 240) }}</code>
                            @else
                                <span class="text-muted">Restricted</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No logs found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
