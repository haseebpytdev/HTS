@extends('layouts.admin')

@section('title', 'Visa Rates')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Visa Rates</h1>
        <a href="{{ route('admin.visa-rates.create') }}" class="btn btn-primary">Add Visa Rate</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.visa-rates.index') }}" class="card card-body border-0 shadow-sm mb-3">
        <div class="row g-2">
            <div class="col-md-3">
                <select name="visa_type_id" class="form-select">
                    <option value="">Visa type</option>
                    @foreach($visaTypes as $visaType)
                        <option value="{{ $visaType->id }}" @selected((string) ($filters['visa_type_id'] ?? '') === (string) $visaType->id)>{{ $visaType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="currency" maxlength="3" class="form-control" value="{{ $filters['currency'] ?? '' }}" placeholder="Currency">
            </div>
            <div class="col-md-2">
                <input type="date" name="valid_from" class="form-control" value="{{ $filters['valid_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="valid_to" class="form-control" value="{{ $filters['valid_to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <select name="is_active" class="form-select">
                    <option value="">Status</option>
                    <option value="1" @selected((string) ($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected((string) ($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('admin.visa-rates.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-striped align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Visa Type</th>
                <th>Currency</th>
                <th>Amount</th>
                <th>Valid From</th>
                <th>Valid To</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($visaRates as $visaRate)
                <tr>
                    <td>{{ $visaRate->id }}</td>
                    <td>{{ $visaRate->visaType?->name ?? '—' }}</td>
                    <td>{{ $visaRate->currency }}</td>
                    <td>{{ number_format((float) $visaRate->amount, 2) }}</td>
                    <td>{{ $visaRate->valid_from?->toDateString() }}</td>
                    <td>{{ $visaRate->valid_to?->toDateString() ?? 'Open' }}</td>
                    <td>
                        @if($visaRate->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.visa-rates.edit', $visaRate) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.visa-rates.destroy', $visaRate) }}" onsubmit="return confirm('Delete this visa rate?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-4">No visa rates found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $visaRates->links() }}</div>
@endsection
