@extends('layouts.agency')

@section('title', 'My Quotations')

@section('agency-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">My Quotations</h1>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search quote/customer">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['draft','sent','approved','rejected'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('agency.quotations.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead><tr><th>Quote #</th><th>Customer</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($quotations as $quotation)
                <tr>
                    <td>{{ $quotation->quote_number }}</td>
                    <td>{{ $quotation->customer_name }}</td>
                    <td>{{ ucfirst($quotation->status) }}</td>
                    <td>{{ number_format((float) $quotation->total_amount, 2) }} {{ $quotation->currency }}</td>
                    <td class="text-end"><a href="{{ route('agency.quotations.show', $quotation) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No quotations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $quotations->links() }}</div>
@endsection
