@extends('layouts.admin')

@section('title', 'Room Type Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Room Type Detail</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.hotel-room-types.edit', $roomType) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.hotel-room-types.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $roomType->name }}</dd>

                <dt class="col-sm-3">Hotel</dt>
                <dd class="col-sm-9">{{ $roomType->hotel?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Sharing basis</dt>
                <dd class="col-sm-9">{{ ucfirst($sharingLabel) }}</dd>

                <dt class="col-sm-3">Capacity</dt>
                <dd class="col-sm-9">{{ $roomType->base_capacity }}</dd>

                <dt class="col-sm-3">Children capacity</dt>
                <dd class="col-sm-9">{{ $roomType->max_children }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($roomType->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
