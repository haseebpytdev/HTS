@extends('layouts.admin')

@section('title', 'Flight Entries')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Flight Entries</h1>
        <a href="{{ route('admin.flights.create') }}" class="btn btn-primary">Add Flight Entry</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.flights.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Search airline/route/flight no">
            </div>
            <div class="col-md-2">
                <input type="text" name="airline" class="form-control" value="{{ $filters['airline'] ?? '' }}" placeholder="Airline">
            </div>
            <div class="col-md-1">
                <input type="text" name="currency" maxlength="3" class="form-control" value="{{ $filters['currency'] ?? '' }}" placeholder="CCY">
            </div>
            <div class="col-md-2">
                <input type="date" name="depart_from" class="form-control" value="{{ $filters['depart_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="depart_to" class="form-control" value="{{ $filters['depart_to'] ?? '' }}">
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
            <a href="{{ route('admin.flights.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Airline</th>
                <th>Route</th>
                <th>Travel Date/Period</th>
                <th>Fare/Cost</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($flightEntries as $entry)
                <tr>
                    <td>{{ $entry->id }}</td>
                    <td>{{ $entry->airline }}</td>
                    <td>{{ $entry->origin }} → {{ $entry->destination }}</td>
                    <td>
                        @if($entry->depart_at)
                            {{ $entry->depart_at->toDateString() }}
                            @if($entry->arrive_at)
                                to {{ $entry->arrive_at->toDateString() }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $entry->currency }} {{ number_format((float) $entry->price, 2) }}</td>
                    <td>
                        @if($entry->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.flights.show', $entry) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.flights.edit', $entry) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.flights.destroy', $entry) }}" onsubmit="return confirm('Delete this flight entry?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">No flight entries found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $flightEntries->links() }}</div>
@endsection
