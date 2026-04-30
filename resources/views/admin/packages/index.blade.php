@extends('layouts.admin')

@section('title', 'Packages')

@section('admin-content')
    <x-ui.admin-page-header
        title="Packages"
        subtitle="Manage package inventory, pricing, flags, and public listing status."
    >
        <x-slot:actions>
            <a href="{{ route('admin.packages.create') }}" class="btn btn-primary">Add Package</a>
        </x-slot:actions>
    </x-ui.admin-page-header>

    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.packages.index') }}" class="card card-body border-0 shadow-sm mb-3 integration-console-filter">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Title">
            </div>
            <div class="col-md-3">
                <select name="destination_id" class="form-select">
                    <option value="">Destination</option>
                    @foreach($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((string) ($filters['destination_id'] ?? '') === (string) $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="category_id" class="form-select">
                    <option value="">Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
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
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('admin.packages.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm integration-console-table">
        <table class="table table-striped align-middle mb-0 as-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Destination</th>
                <th>Category</th>
                <th>Price</th>
                <th>Flags</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                <tr>
                    <td>{{ $package->id }}</td>
                    <td>{{ $package->title }}</td>
                    <td>{{ $package->destination?->name ?? '—' }}</td>
                    <td>{{ $package->category?->name ?? '—' }}</td>
                    <td>{{ $package->currency }} {{ number_format((float) $package->base_price, 2) }}</td>
                    <td>
                        @if($package->is_featured)
                            <x-ui.badge tone="warning">Featured</x-ui.badge>
                        @endif
                        @if($package->is_active)
                            <x-ui.badge tone="success">Active</x-ui.badge>
                        @else
                            <x-ui.badge tone="muted">Inactive</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-ui.action-menu class="justify-content-end">
                            <a href="{{ route('admin.packages.show', $package) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="{{ route('admin.packages.gallery.index', $package) }}" class="btn btn-sm btn-outline-info">Gallery</a>
                            <a href="{{ route('frontend.packages.show', $package->slug) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Public</a>
                            <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </x-ui.action-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-4">
                        <x-ui.empty-state title="No packages found" message="Adjust filters or create a new package to get started." icon="bi-suitcase2" />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-ui.pagination :paginator="$packages" />
@endsection
