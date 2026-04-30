@extends('layouts.admin')

@section('title', 'Flight Entry Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Flight Entry Detail</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.flights.edit', $flightEntry) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.flights.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Airline</dt>
                <dd class="col-sm-9">{{ $flightEntry->airline }}</dd>

                <dt class="col-sm-3">Route</dt>
                <dd class="col-sm-9">{{ $flightEntry->origin }} → {{ $flightEntry->destination }}</dd>

                <dt class="col-sm-3">Travel date / period</dt>
                <dd class="col-sm-9">
                    @if($flightEntry->depart_at)
                        {{ $flightEntry->depart_at->toDateTimeString() }}
                        @if($flightEntry->arrive_at)
                            to {{ $flightEntry->arrive_at->toDateTimeString() }}
                        @endif
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">Fare / Cost</dt>
                <dd class="col-sm-9">{{ $flightEntry->currency }} {{ number_format((float) $flightEntry->price, 2) }}</dd>

                <dt class="col-sm-3">Notes</dt>
                <dd class="col-sm-9">{{ $flightEntry->flight_no ?? '—' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($flightEntry->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
