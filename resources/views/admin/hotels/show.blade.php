@extends('layouts.admin')

@section('title', 'Hotel Details')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $hotel->name }}</h1>
            <small class="text-muted">Hotel details</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.hotels.edit', $hotel) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.hotels.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $hotel->name }}</dd>

                <dt class="col-sm-3">City</dt>
                <dd class="col-sm-9">{{ $hotel->city ?? '—' }}</dd>

                <dt class="col-sm-3">Address</dt>
                <dd class="col-sm-9">{{ $hotel->address ?? '—' }}</dd>

                <dt class="col-sm-3">Star rating</dt>
                <dd class="col-sm-9">{{ $hotel->star_rating ?? '—' }}</dd>

                <dt class="col-sm-3">Distance from Haram (km)</dt>
                <dd class="col-sm-9">
                    @if(isset($hotel->distance_from_haram_km))
                        {{ number_format((float) $hotel->distance_from_haram_km, 2) }}
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($hotel->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
