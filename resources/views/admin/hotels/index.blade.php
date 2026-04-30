@extends('layouts.admin')

@section('title', 'Hotels')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Hotels</h1>
            <small class="text-muted">Manage hotel master data.</small>
        </div>
        <a href="{{ route('admin.hotels.create') }}" class="btn btn-primary">Add Hotel</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.hotels.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by name, city, or address"
                >
            </div>
            <div class="col-md-3">
                <input type="text" name="city" class="form-control" value="{{ $filters['city'] ?? '' }}" placeholder="City">
            </div>
            <div class="col-md-2">
                <select name="star_rating" class="form-select">
                    <option value="">Star rating</option>
                    @for($i = 1; $i <= 7; $i++)
                        <option value="{{ $i }}" @selected((string) ($filters['star_rating'] ?? '') === (string) $i)>{{ $i }} Star</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
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
            <a href="{{ route('admin.hotels.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>City</th>
                <th>Star</th>
                <th>Distance from Haram (km)</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($hotels as $hotel)
                <tr>
                    <td>{{ $hotel->id }}</td>
                    <td>{{ $hotel->name }}</td>
                    <td>{{ $hotel->city ?? '—' }}</td>
                    <td>{{ $hotel->star_rating ?? '—' }}</td>
                    <td>
                        @if(isset($hotel->distance_from_haram_km))
                            {{ number_format((float) $hotel->distance_from_haram_km, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($hotel->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.hotels.show', $hotel) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.hotels.edit', $hotel) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.hotels.destroy', $hotel) }}" onsubmit="return confirm('Delete this hotel?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">No hotels found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $hotels->links() }}
    </div>
@endsection
