@extends('layouts.admin')

@section('title', 'Hotel Room Types')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Hotel Room Types</h1>
            <small class="text-muted">Manage room type inventory by hotel.</small>
        </div>
        <a href="{{ route('admin.hotel-room-types.create') }}" class="btn btn-primary">Add Room Type</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.hotel-room-types.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Room type name">
            </div>
            <div class="col-md-3">
                <select name="hotel_id" class="form-select">
                    <option value="">Hotel</option>
                    @foreach($hotels as $hotel)
                        <option value="{{ $hotel->id }}" @selected((string) ($filters['hotel_id'] ?? '') === (string) $hotel->id)>{{ $hotel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="sharing_basis" class="form-select">
                    <option value="">Sharing</option>
                    @foreach($sharingOptions as $label => $capacity)
                        <option value="{{ $label }}" @selected(($filters['sharing_basis'] ?? '') === $label)>{{ ucfirst($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_active" class="form-select">
                    <option value="">Status</option>
                    <option value="1" @selected((string) ($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected((string) ($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('admin.hotel-room-types.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Hotel</th>
                <th>Sharing Basis</th>
                <th>Capacity</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($roomTypes as $roomType)
                <tr>
                    <td>{{ $roomType->id }}</td>
                    <td>{{ $roomType->name }}</td>
                    <td>{{ $roomType->hotel?->name ?? '—' }}</td>
                    <td>{{ ucfirst(array_search((int) $roomType->base_capacity, $sharingOptions, true) ?: 'double') }}</td>
                    <td>{{ $roomType->base_capacity }}</td>
                    <td>
                        @if($roomType->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.hotel-room-types.show', $roomType) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.hotel-room-types.edit', $roomType) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.hotel-room-types.destroy', $roomType) }}" onsubmit="return confirm('Delete this room type?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">No room types found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $roomTypes->links() }}</div>
@endsection
