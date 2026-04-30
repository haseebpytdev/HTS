@extends('layouts.admin')

@section('title', 'Package Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $package->title }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.packages.gallery.index', $package) }}" class="btn btn-outline-info">Gallery</a>
            <a href="{{ route('frontend.packages.show', $package->slug) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">Public</a>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-outline-dark">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Slug</dt>
                <dd class="col-sm-9"><code>{{ $package->slug }}</code></dd>

                <dt class="col-sm-3">Destination</dt>
                <dd class="col-sm-9">{{ $package->destination?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Category</dt>
                <dd class="col-sm-9">{{ $package->category?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Price</dt>
                <dd class="col-sm-9">{{ $package->currency }} {{ number_format((float) $package->base_price, 2) }}</dd>

                <dt class="col-sm-3">Overview</dt>
                <dd class="col-sm-9">{{ $overview !== '' ? $overview : '—' }}</dd>

                <dt class="col-sm-3">Itinerary</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $itinerary !== '' ? $itinerary : '—' }}</pre></dd>

                <dt class="col-sm-3">Inclusions</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $inclusions !== '' ? $inclusions : '—' }}</pre></dd>

                <dt class="col-sm-3">Exclusions</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $exclusions !== '' ? $exclusions : '—' }}</pre></dd>

                <dt class="col-sm-3">Flags</dt>
                <dd class="col-sm-9">
                    @if($package->is_featured)
                        <span class="badge text-bg-warning">Featured</span>
                    @endif
                    @if($package->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
