@extends('layouts.admin')

@section('title', 'Edit Agency')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Edit Agency</h1>
        <small class="text-muted">Update agency details.</small>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <x-admin.agency-form
        :agency="$agency"
        :action="route('admin.agencies.update', $agency)"
        method="PUT"
        submit-label="Update Agency"
    />

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h6 mb-2">Agency wallet</h2>
            <p class="small text-muted mb-2">
                Balance: <strong>{{ number_format((float) $wallet->balance, 2) }}</strong> {{ $wallet->currency }}
                @if($wallet->credit_limit !== null)
                    · Credit limit: {{ number_format((float) $wallet->credit_limit, 2) }}
                @endif
                @if($wallet->is_overdue)
                    <span class="badge text-bg-warning ms-1">Overdue</span>
                @endif
            </p>
            <form method="POST" action="{{ route('admin.agencies.wallet.top-up', $agency) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small">Top-up amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Currency</label>
                    <input type="text" name="currency" class="form-control form-control-sm" maxlength="3" value="{{ old('currency', $wallet->currency) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Idempotency (optional)</label>
                    <input type="text" name="idempotency_key" class="form-control form-control-sm" value="{{ old('idempotency_key') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Top up</button>
                </div>
                @error('amount')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>
@endsection
