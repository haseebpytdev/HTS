@extends('layouts.admin')

@section('title', 'Transport Rates')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Transport Rates</h1>
        <a href="{{ route('admin.transport-rates.create') }}" class="btn btn-primary">Add Transport Rate</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.transport-rates.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-2">
                <select name="transport_type_id" class="form-select">
                    <option value="">Type</option>
                    @foreach($transportTypes as $transportType)
                        <option value="{{ $transportType->id }}" @selected((string) ($filters['transport_type_id'] ?? '') === (string) $transportType->id)>{{ $transportType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="label" class="form-control" value="{{ $filters['label'] ?? '' }}" placeholder="Route/label">
            </div>
            <div class="col-md-2">
                <input type="text" name="currency" class="form-control" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="Currency">
            </div>
            <div class="col-md-2">
                <input type="date" name="valid_from" class="form-control" value="{{ $filters['valid_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="valid_to" class="form-control" value="{{ $filters['valid_to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <select name="is_active" class="form-select">
                    <option value="">Status</option>
                    <option value="1" @selected((string) ($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected((string) ($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('admin.transport-rates.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Type</th>
                <th>Label</th>
                <th>Route</th>
                <th>Validity</th>
                <th>Currency</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($transportRates as $transportRate)
                <tr>
                    <td>{{ $transportRate->id }}</td>
                    <td>{{ $transportRate->transportType?->name ?? '—' }}</td>
                    <td>{{ $transportRate->vehicle_name }}</td>
                    <td>{{ trim(($transportRate->route_from ?? '').' -> '.($transportRate->route_to ?? ''), ' ->') ?: '—' }}</td>
                    <td>{{ $transportRate->valid_from?->toDateString() }} to {{ $transportRate->valid_to?->toDateString() ?? 'Open' }}</td>
                    <td>{{ $transportRate->currency }}</td>
                    <td>{{ number_format((float) $transportRate->amount, 2) }}</td>
                    <td>
                        @if($transportRate->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.transport-rates.edit', $transportRate) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.transport-rates.destroy', $transportRate) }}" onsubmit="return confirm('Delete this transport rate?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4">No transport rates found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $transportRates->links() }}</div>
@endsection
