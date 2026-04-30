@extends('layouts.admin')

@section('title', 'Group Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $group->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.groups.gallery.index', $group) }}" class="btn btn-outline-info">Gallery</a>
            <a href="{{ route('frontend.groups.show', $group->slug) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">Public</a>
            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-dark">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Destination</dt>
                <dd class="col-sm-9">{{ $group->package?->destination?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Departure/Return</dt>
                <dd class="col-sm-9">{{ $group->departure_date?->toDateString() ?? '—' }} to {{ $group->return_date?->toDateString() ?? '—' }}</dd>

                <dt class="col-sm-3">Seats</dt>
                <dd class="col-sm-9">{{ $group->seats_left ?? 0 }}/{{ $group->capacity ?? 0 }}</dd>

                <dt class="col-sm-3">Pricing tiers</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $pricing_tiers !== '' ? $pricing_tiers : ($group->group_type ?? '—') }}</pre></dd>

                <dt class="col-sm-3">Airline info</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $airline_info !== '' ? $airline_info : '—' }}</pre></dd>

                <dt class="col-sm-3">Hotel info</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $hotel_info !== '' ? $hotel_info : '—' }}</pre></dd>

                <dt class="col-sm-3">Notes</dt>
                <dd class="col-sm-9"><pre class="mb-0">{{ $notes_text !== '' ? $notes_text : '—' }}</pre></dd>

                <dt class="col-sm-3">Flags</dt>
                <dd class="col-sm-9">
                    @if($group->status === 'featured')
                        <span class="badge text-bg-warning">Featured</span>
                    @endif
                    @if($group->status !== 'closed')
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
