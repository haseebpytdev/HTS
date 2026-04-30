@php
    $editing = isset($flightEntry) && $flightEntry->exists;
    $action = $editing ? route('admin.flights.update', $flightEntry) : route('admin.flights.store');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="col-md-3">
                <label class="form-label">Airline</label>
                <input type="text" name="airline" class="form-control @error('airline') is-invalid @enderror" value="{{ old('airline', $flightEntry->airline ?? '') }}" required>
                @error('airline')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Origin</label>
                <input type="text" name="origin" maxlength="3" class="form-control @error('origin') is-invalid @enderror" value="{{ old('origin', $flightEntry->origin ?? '') }}" required>
                @error('origin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Destination</label>
                <input type="text" name="destination" maxlength="3" class="form-control @error('destination') is-invalid @enderror" value="{{ old('destination', $flightEntry->destination ?? '') }}" required>
                @error('destination')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Currency</label>
                <input type="text" name="currency" maxlength="3" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $flightEntry->currency ?? 'PKR') }}" required>
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Fare/Cost</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $flightEntry->price ?? '') }}" required>
                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Travel date (depart)</label>
                <input type="datetime-local" name="depart_at" class="form-control @error('depart_at') is-invalid @enderror" value="{{ old('depart_at', isset($flightEntry?->depart_at) ? $flightEntry->depart_at->format('Y-m-d\TH:i') : '') }}">
                @error('depart_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Validity end (arrive)</label>
                <input type="datetime-local" name="arrive_at" class="form-control @error('arrive_at') is-invalid @enderror" value="{{ old('arrive_at', isset($flightEntry?->arrive_at) ? $flightEntry->arrive_at->format('Y-m-d\TH:i') : '') }}">
                @error('arrive_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Flight No</label>
                <input type="text" name="flight_no" class="form-control @error('flight_no') is-invalid @enderror" value="{{ old('flight_no', $flightEntry->flight_no ?? '') }}">
                @error('flight_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Cabin</label>
                <select name="cabin_class" class="form-select @error('cabin_class') is-invalid @enderror">
                    @foreach(['economy' => 'Economy', 'premium_economy' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('cabin_class', $flightEntry->cabin_class ?? 'economy') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('cabin_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Seats</label>
                <input type="number" min="0" max="999" name="seats_available" class="form-control @error('seats_available') is-invalid @enderror" value="{{ old('seats_available', $flightEntry->seats_available ?? '') }}">
                @error('seats_available')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $flightEntry->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save Changes' : 'Create Flight Entry' }}</button>
                <a href="{{ route('admin.flights.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
