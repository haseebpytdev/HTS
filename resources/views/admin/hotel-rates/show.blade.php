@extends('layouts.admin')

@section('title', 'Hotel Rate Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Hotel Rate Detail</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.hotel-rates.edit', $hotelRate) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.hotel-rates.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Hotel</dt>
                <dd class="col-sm-9">{{ $hotelRate->roomType?->hotel?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Room type</dt>
                <dd class="col-sm-9">{{ $hotelRate->roomType?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Season name</dt>
                <dd class="col-sm-9">{{ $hotelRate->meal_plan }}</dd>

                <dt class="col-sm-3">Validity</dt>
                <dd class="col-sm-9">{{ $hotelRate->valid_from?->toDateString() }} to {{ $hotelRate->valid_to?->toDateString() ?? 'Open' }}</dd>

                <dt class="col-sm-3">Currency</dt>
                <dd class="col-sm-9">{{ $hotelRate->currency }}</dd>

                <dt class="col-sm-3">Rate per night</dt>
                <dd class="col-sm-9">{{ number_format((float) $hotelRate->rate_per_night, 2) }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($hotelRate->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
