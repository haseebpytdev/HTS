@extends('layouts.admin')

@section('title', 'Quotations')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Quotations</h1>
            <small class="text-muted">Internal staff quotation builder records.</small>
        </div>
        <a href="{{ route('admin.quotations.create') }}" class="btn btn-success">Create Quotation</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.quotations.index') }}" class="row g-2 mb-3 card card-body border-0 shadow-sm">
        <div class="col-md-4">
            <input type="text" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search quote no/customer">
        </div>
        <div class="col-md-3">
            <select name="agency_id" class="form-select">
                <option value="">All Agencies</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? '') == $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                @foreach(['draft','sent','approved','rejected'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.quotations.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Agency</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $quotation)
                    <tr>
                        <td>{{ $quotation->quote_number }}</td>
                        <td>{{ $quotation->agency?->name }}</td>
                        <td>{{ $quotation->customer_name }}</td>
                        <td><span class="badge text-bg-light">{{ ucfirst($quotation->status) }}</span></td>
                        <td>{{ number_format((float) $quotation->total_amount, 2) }} {{ $quotation->currency }}</td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-1 action-cluster">
                            <a href="{{ route('admin.quotations.show', $quotation) }}" class="btn btn-sm btn-outline-dark">View</a>
                            <a href="{{ route('admin.quotations.edit', $quotation) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">No quotations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $quotations->links() }}</div>
@endsection
