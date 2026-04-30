@extends('layouts.admin')

@section('title', 'Inquiries')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Inquiries</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.exports.inquiries-csv', request()->query()) }}" class="btn btn-sm btn-outline-success">Export Inquiries CSV</a>
            <a href="{{ route('admin.exports.bookings-csv', request()->query()) }}" class="btn btn-sm btn-outline-primary">Export Bookings CSV</a>
            <a href="{{ route('admin.export-history.index') }}" class="btn btn-sm btn-outline-secondary">Export history</a>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, email, phone">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="pipeline_stage" class="form-select">
                <option value="">All pipeline</option>
                @foreach($pipelineStages as $stage)
                    <option value="{{ $stage->value }}" @selected(($filters['pipeline_stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="source" class="form-select">
                <option value="">All sources</option>
                @foreach(['quote', 'package', 'group'] as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ ucfirst($source) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="agency_id" class="form-select">
                <option value="">All agencies</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? null) == $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="package_id" class="form-select">
                <option value="">All packages</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" @selected(($filters['package_id'] ?? null) == $package->id)>{{ $package->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="destination_id" class="form-select">
                <option value="">All destinations</option>
                @foreach($destinations as $destination)
                    <option value="{{ $destination->id }}" @selected(($filters['destination_id'] ?? null) == $destination->id)>{{ $destination->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="assigned_to" class="form-select">
                <option value="">Any assignee</option>
                @foreach($assignableUsers as $user)
                    <option value="{{ $user->id }}" @selected(($filters['assigned_to'] ?? null) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="has_quotation" class="form-select">
                <option value="">Quotes: Any</option>
                <option value="1" @selected(($filters['has_quotation'] ?? '') === '1')>With quote</option>
                <option value="0" @selected(($filters['has_quotation'] ?? '') === '0')>Without quote</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="has_open_follow_up" class="form-select">
                <option value="">Follow-up: Any</option>
                <option value="1" @selected(($filters['has_open_follow_up'] ?? '') === '1')>Open follow-up</option>
                <option value="0" @selected(($filters['has_open_follow_up'] ?? '') === '0')>No open follow-up</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="created_from" class="form-control" value="{{ $filters['created_from'] ?? '' }}" title="Created from">
        </div>
        <div class="col-md-2">
            <input type="date" name="created_to" class="form-control" value="{{ $filters['created_to'] ?? '' }}" title="Created to">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Lead</th>
                <th>Source</th>
                <th>Status</th>
                <th>Pipeline</th>
                <th>Assigned</th>
                <th>Notes</th>
                <th>Quote Link</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($inquiries as $inquiry)
                <tr>
                    <td>{{ $inquiry->id }}</td>
                    <td>
                        <div class="fw-semibold">{{ $inquiry->name }}</div>
                        <small class="text-muted">{{ $inquiry->email ?: '-' }} | {{ $inquiry->phone ?: '-' }}</small>
                    </td>
                    <td>{{ ucfirst($inquiry->source) }}</td>
                    <td><span class="badge text-bg-light">{{ str_replace('_', ' ', ucfirst($inquiry->status)) }}</span></td>
                    <td><span class="badge text-bg-secondary">{{ $inquiry->pipeline_stage->label() }}</span></td>
                    <td>{{ $inquiry->assignedTo?->name ?? 'Unassigned' }}</td>
                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) ($inquiry->admin_notes ?? ''), 70) ?: '-' }}</td>
                    <td>
                        @if($inquiry->quotations->isNotEmpty())
                            {{ $inquiry->quotations->pluck('quote_number')->join(', ') }}
                            <div class="small text-success">{{ $inquiry->quotations_count }} quote(s)</div>
                        @else
                            <span class="text-muted">Not quoted</span>
                        @endif
                        @if($inquiry->follow_ups_count > 0)
                            <div class="small text-muted">{{ $inquiry->follow_ups_count }} follow-up(s)</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                            @if($inquiry->quotations->isNotEmpty())
                                <a href="{{ route('admin.inquiries.show', $inquiry) }}#convert-intent" class="btn btn-sm btn-outline-success">Convert</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4">No inquiries found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $inquiries->links() }}</div>
@endsection
