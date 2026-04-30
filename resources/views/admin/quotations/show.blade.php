@extends('layouts.admin')

@section('title', 'Quotation Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Quotation {{ $quotation->quote_number }}</h1>
            <small class="text-muted">{{ ucfirst($quotation->status) }} | {{ $quotation->agency?->name }}</small>
        </div>
        <div class="d-flex flex-wrap gap-2 action-cluster">
            <a href="{{ route('admin.quotations.edit', $quotation) }}" class="btn btn-outline-primary">Edit</a>
            <form method="POST" action="{{ route('admin.quotations.duplicate', $quotation) }}">
                @csrf
                <button class="btn btn-outline-success">Duplicate</button>
            </form>
            <a href="{{ route('admin.quotations.print', $quotation) }}" class="btn btn-dark" target="_blank">Print</a>
            <a href="{{ route('admin.quotations.pdf', $quotation) }}" class="btn btn-outline-dark">Export PDF</a>
            <form method="POST" action="{{ route('admin.quotations.bookings.store', $quotation) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary">Create booking</button>
            </form>
            <a href="{{ route('admin.export-history.index') }}" class="btn btn-link btn-sm text-secondary">Export history</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6">Customer</h2>
                    <p class="mb-1"><strong>Name:</strong> {{ $quotation->customer_name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $quotation->customer_email }}</p>
                    <p class="mb-0"><strong>Phone:</strong> {{ $quotation->customer_phone }}</p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Items</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th class="text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($quotation->items as $item)
                                <tr>
                                    <td>{{ $item->item_type }}</td>
                                    <td>{{ $item->title }}</td>
                                    <td class="text-end">{{ number_format((float) $item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <x-shared.quote-summary-panel :totals="[
                'subtotal' => $quotation->subtotal,
                'markup' => (float) $quotation->items->where('item_type', 'markup')->sum('total_price'),
                'discount' => $quotation->discount_amount,
                'grand_total' => $quotation->total_amount,
                'per_person' => (($quotation->adults + $quotation->children) > 0) ? round($quotation->total_amount / ($quotation->adults + $quotation->children), 2) : 0,
                'makkah_hotel' => (float) $quotation->items->where('item_type', 'hotel_makkah')->sum('total_price'),
                'madinah_hotel' => (float) $quotation->items->where('item_type', 'hotel_madinah')->sum('total_price'),
                'visa' => (float) $quotation->items->where('item_type', 'visa')->sum('total_price'),
                'transport' => (float) $quotation->items->where('item_type', 'transport')->sum('total_price'),
                'flight' => (float) $quotation->items->where('item_type', 'flight')->sum('total_price'),
                'extras' => (float) $quotation->items->where('item_type', 'extras')->sum('total_price'),
            ]" />
            @if($quotation->promo_code)
                <div class="alert alert-info small mt-3 mb-0">
                    Promo applied: <strong>{{ $quotation->promo_code }}</strong>
                    ({{ number_format((float) ($quotation->promo_discount_amount ?? 0), 2) }} {{ $quotation->currency }})
                </div>
            @endif
        </div>
    </div>
@endsection
