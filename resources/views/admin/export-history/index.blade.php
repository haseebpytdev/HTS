@extends('layouts.admin')

@section('title', 'Export History')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Export History</h1>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">Dashboard</a>
    </div>

    <p class="text-muted small mb-3">Audit log of PDF and CSV exports from the admin area (who, what, when).</p>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Export</th>
                <th>Reference</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            @forelse($history as $row)
                <tr>
                    <td class="text-nowrap">{{ $row->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $row->user?->name ?? '—' }}<br><small class="text-muted">{{ $row->user?->email }}</small></td>
                    <td>
                        @switch($row->export_type)
                            @case(\App\Models\ExportHistory::TYPE_QUOTATION_PDF)
                                Quotation PDF
                                @break
                            @case(\App\Models\ExportHistory::TYPE_INQUIRIES_CSV)
                                Inquiries CSV
                                @break
                            @case(\App\Models\ExportHistory::TYPE_BOOKINGS_CSV)
                                Bookings CSV
                                @break
                            @default
                                {{ $row->export_type }}
                        @endswitch
                    </td>
                    <td>
                        @if($row->reference)
                            {{ class_basename($row->reference_type) }} #{{ $row->reference_id }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <small>
                            @if($row->meta)
                                @if(! empty($row->meta['file_name']))
                                    {{ $row->meta['file_name'] }}
                                @endif
                                @if(! empty($row->meta['quote_number']))
                                    <span class="text-muted"> · {{ $row->meta['quote_number'] }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </small>
                    </td>
                    <td><small>{{ $row->ip_address ?? '—' }}</small></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No exports recorded yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $history->links() }}</div>
@endsection
