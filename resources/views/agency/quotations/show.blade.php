@extends('layouts.agency')

@section('title', 'Quotation Detail')

@section('agency-content')
    <h1 class="h4 mb-3">Quotation {{ $quotation->quote_number }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Customer:</strong> {{ $quotation->customer_name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $quotation->customer_email }}</p>
                    <p class="mb-0"><strong>Phone:</strong> {{ $quotation->customer_phone }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Status:</strong> {{ ucfirst($quotation->status) }}</p>
                    <p class="mb-1"><strong>Total:</strong> {{ number_format((float) $quotation->total_amount, 2) }} {{ $quotation->currency }}</p>
                    <p class="mb-0"><strong>Travel:</strong> {{ optional($quotation->travel_date)->format('d M Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Quotation Items</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Type</th><th>Title</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                @foreach($quotation->items as $item)
                                    <tr>
                                        <td>{{ strtoupper(str_replace('_', ' ', $item->item_type)) }}</td>
                                        <td>{{ $item->title }}</td>
                                        <td class="text-end">{{ number_format((float) $item->total_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Request Revision</h2>
                    <form method="POST" action="{{ route('agency.quotations.revision', $quotation) }}">
                        @csrf
                        <textarea name="message" rows="3" class="form-control mb-2" placeholder="Explain what should be revised..." required></textarea>
                        <button class="btn btn-outline-primary">Send Revision Request</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Booking Intent</h2>
                    <form method="POST" action="{{ route('agency.quotations.booking-intent', $quotation) }}">
                        @csrf
                        <textarea name="note" rows="2" class="form-control mb-2" placeholder="Optional booking note"></textarea>
                        <button class="btn btn-success">Submit Booking Intent</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Revision History</h2>
                    <ul class="list-group list-group-flush">
                        @forelse($quotation->revisionRequests as $row)
                            <li class="list-group-item px-0">
                                <div class="small text-muted">{{ $row->requested_at?->format('d M Y H:i') }} by {{ $row->user?->name }}</div>
                                <div>{{ $row->message }}</div>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">No revision requests yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Booking Intent History</h2>
                    <ul class="list-group list-group-flush">
                        @forelse($quotation->bookingIntents as $row)
                            <li class="list-group-item px-0">
                                <div class="small text-muted">{{ $row->requested_at?->format('d M Y H:i') }} by {{ $row->user?->name }}</div>
                                <div>{{ $row->note ?: 'No note' }}</div>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">No booking intents yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
