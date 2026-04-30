@extends('layouts.admin')

@section('title', 'Hotel Rates')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Hotel Rates</h1>
            <small class="text-muted">Manage seasonal hotel rate cards.</small>
        </div>
        <a href="{{ route('admin.hotel-rates.create') }}" class="btn btn-primary">Add Hotel Rate</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.hotel-rates.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-2">
                <select name="hotel_id" class="form-select">
                    <option value="">Hotel</option>
                    @foreach($hotels as $hotel)
                        <option value="{{ $hotel->id }}" @selected((string) ($filters['hotel_id'] ?? '') === (string) $hotel->id)>{{ $hotel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="hotel_room_type_id" class="form-select">
                    <option value="">Room Type</option>
                    @foreach($roomTypes as $roomType)
                        <option value="{{ $roomType->id }}" @selected((string) ($filters['hotel_room_type_id'] ?? '') === (string) $roomType->id)>{{ $roomType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="season_name" class="form-control" value="{{ $filters['season_name'] ?? '' }}" placeholder="Season name">
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
            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('admin.hotel-rates.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Hotel</th>
                <th>Room Type</th>
                <th>Season</th>
                <th>Validity</th>
                <th>Currency</th>
                <th>Rate / Night</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rates as $rate)
                <tr>
                    <td>{{ $rate->id }}</td>
                    <td>{{ $rate->roomType?->hotel?->name ?? '—' }}</td>
                    <td>{{ $rate->roomType?->name ?? '—' }}</td>
                    <td>{{ $rate->meal_plan }}</td>
                    <td>{{ $rate->valid_from?->toDateString() }} to {{ $rate->valid_to?->toDateString() ?? 'Open' }}</td>
                    <td>{{ $rate->currency }}</td>
                    <td>{{ number_format((float) $rate->rate_per_night, 2) }}</td>
                    <td>
                        @if($rate->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.hotel-rates.show', $rate) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.hotel-rates.edit', $rate) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.hotel-rates.destroy', $rate) }}" onsubmit="return confirm('Delete this rate?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4">No hotel rates found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $rates->links() }}</div>
@endsection
