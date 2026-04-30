@extends('layouts.agency')

@section('title', 'Agency Dashboard')

@section('agency-content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Agency Dashboard</h1>
        <a href="{{ route('frontend.home') }}" class="btn btn-sm btn-outline-primary">Go to Home Page</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Total Quotations</small><div class="h4 mb-0">{{ $stats['quotations_total'] }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Pending Quotations</small><div class="h4 mb-0">{{ $stats['quotations_pending'] }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Approved Quotations</small><div class="h4 mb-0">{{ $stats['booked_intents'] }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Inquiries</small><div class="h4 mb-0">{{ $stats['inquiries_total'] }}</div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">Latest Quotations</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Quote #</th><th>Status</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        @forelse($latestQuotations as $quotation)
                            <tr>
                                <td>{{ $quotation->quote_number }}</td>
                                <td>{{ ucfirst($quotation->status) }}</td>
                                <td>{{ number_format((float) $quotation->total_amount, 2) }} {{ $quotation->currency }}</td>
                                <td class="text-end"><a href="{{ route('agency.quotations.show', $quotation) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No quotations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
