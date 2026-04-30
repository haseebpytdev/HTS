@extends('layouts.admin')

@section('title', 'Groups')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Groups</h1>
        <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">Add Group</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.groups.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-2">
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Name">
            </div>
            <div class="col-md-2">
                <select name="destination_id" class="form-select">
                    <option value="">Destination</option>
                    @foreach($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((string) ($filters['destination_id'] ?? '') === (string) $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="package_id" class="form-select">
                    <option value="">Package</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" @selected((string) ($filters['package_id'] ?? '') === (string) $package->id)>{{ $package->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="departure_from" class="form-control" value="{{ $filters['departure_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="departure_to" class="form-control" value="{{ $filters['departure_to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <select name="is_featured" class="form-select">
                    <option value="">Featured</option>
                    <option value="1" @selected((string) ($filters['is_featured'] ?? '') === '1')>Yes</option>
                    <option value="0" @selected((string) ($filters['is_featured'] ?? '') === '0')>No</option>
                </select>
            </div>
            <div class="col-md-1">
                <select name="is_active" class="form-select">
                    <option value="">Status</option>
                    <option value="1" @selected((string) ($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected((string) ($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Destination</th>
                <th>Dates</th>
                <th>Seats</th>
                <th>Flags</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>{{ $group->id }}</td>
                    <td>{{ $group->name }}</td>
                    <td>{{ $group->package?->destination?->name ?? '—' }}</td>
                    <td>{{ $group->departure_date?->toDateString() ?? '—' }} to {{ $group->return_date?->toDateString() ?? '—' }}</td>
                    <td>{{ $group->seats_left ?? 0 }}/{{ $group->capacity ?? 0 }}</td>
                    <td>
                        @if($group->status === 'featured')
                            <span class="badge text-bg-warning">Featured</span>
                        @endif
                        @if($group->status !== 'closed')
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="{{ route('admin.groups.gallery.index', $group) }}" class="btn btn-sm btn-outline-info">Gallery</a>
                            <a href="{{ route('frontend.groups.show', $group->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Public</a>
                            <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" onsubmit="return confirm('Delete this group?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">No groups found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $groups->links() }}</div>
@endsection
