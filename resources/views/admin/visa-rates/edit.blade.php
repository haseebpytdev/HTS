@extends('layouts.admin')

@section('title', 'Edit Visa Rate')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Edit Visa Rate</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.visa-rates.update', $visaRate) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="form-label">Visa type</label>
                    <select name="visa_type_id" class="form-select @error('visa_type_id') is-invalid @enderror" required>
                        <option value="">Select visa type</option>
                        @foreach($visaTypes as $visaType)
                            <option value="{{ $visaType->id }}" @selected((string) old('visa_type_id', $visaRate->visa_type_id) === (string) $visaType->id)>{{ $visaType->name }}</option>
                        @endforeach
                    </select>
                    @error('visa_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" maxlength="3" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $visaRate->currency) }}" required>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $visaRate->amount) }}" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $visaRate->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valid from</label>
                    <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from', $visaRate->valid_from?->toDateString()) }}" required>
                    @error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valid to</label>
                    <input type="date" name="valid_to" class="form-control @error('valid_to') is-invalid @enderror" value="{{ old('valid_to', $visaRate->valid_to?->toDateString()) }}">
                    @error('valid_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.visa-rates.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
