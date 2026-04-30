@extends('layouts.admin')

@section('title', 'Create Transport Rate')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Transport Rate</h1>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.transport-rates.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Transport type</label>
                    <select name="transport_type_id" class="form-select @error('transport_type_id') is-invalid @enderror" required>
                        <option value="">Select type</option>
                        @foreach($transportTypes as $transportType)
                            <option value="{{ $transportType->id }}" @selected((string) old('transport_type_id') === (string) $transportType->id)>{{ $transportType->name }}</option>
                        @endforeach
                    </select>
                    @error('transport_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Route / Label</label>
                    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label') }}" required>
                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" maxlength="3" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', 'PKR') }}" required>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Route from</label>
                    <input type="text" name="route_from" class="form-control @error('route_from') is-invalid @enderror" value="{{ old('route_from') }}">
                    @error('route_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Route to</label>
                    <input type="text" name="route_to" class="form-control @error('route_to') is-invalid @enderror" value="{{ old('route_to') }}">
                    @error('route_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trip type</label>
                    <select name="trip_type" class="form-select @error('trip_type') is-invalid @enderror">
                        <option value="">Select trip type</option>
                        <option value="one_way" @selected(old('trip_type') === 'one_way')>One way</option>
                        <option value="round_trip" @selected(old('trip_type') === 'round_trip')>Round trip</option>
                    </select>
                    @error('trip_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', true))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Valid from</label>
                    <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from') }}" required>
                    @error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valid to</label>
                    <input type="date" name="valid_to" class="form-control @error('valid_to') is-invalid @enderror" value="{{ old('valid_to') }}">
                    @error('valid_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Transport Rate</button>
                    <a href="{{ route('admin.transport-rates.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
