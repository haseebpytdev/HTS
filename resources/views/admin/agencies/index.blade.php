@extends('layouts.admin')

@section('title', 'Agencies')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Agencies</h1>
            <small class="text-muted">Manage agency master data.</small>
        </div>
        <a href="{{ route('admin.agencies.create') }}" class="btn btn-success">Add Agency</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <x-filters.agency-filter-form :filters="$filters" />

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agencies as $agency)
                    <tr>
                        <td>{{ $agency->id }}</td>
                        <td>{{ $agency->name }}</td>
                        <td><span class="badge text-bg-light">{{ $agency->code }}</span></td>
                        <td>
                            @if($agency->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.agencies.edit', $agency) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.agencies.destroy', $agency) }}" onsubmit="return confirm('Delete this agency?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4">No agencies found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $agencies->links() }}
    </div>
@endsection
