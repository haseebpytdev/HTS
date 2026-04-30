<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking voucher {{ $booking->booking_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; font-size: 13px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 12px; gap: 24px; }
        .brand { font-size: 20px; margin: 0 0 6px; }
        .text-muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        @media print { body { margin: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 14px; text-align: right;">
        <button type="button" onclick="window.print()" style="padding: 8px 12px; border: 1px solid #d1d5db; background: #fff; cursor: pointer;">Print</button>
    </div>
    <div class="row">
        <div>
            <h2 class="brand">{{ config('brand.name') }} — Booking voucher</h2>
            <p class="text-muted mb-0">Traveler-facing confirmation (non-financial).</p>
        </div>
        <div>
            <p class="mb-1"><strong>Booking #</strong> {{ $booking->booking_number }}</p>
            <p class="mb-1"><strong>Status</strong> {{ str_replace('_', ' ', $booking->status) }}</p>
            <p class="mb-0"><strong>Agency</strong> {{ $booking->agency?->name ?? '—' }}</p>
        </div>
    </div>
    <table>
        <tr><th>Customer</th><td>{{ $booking->customer_name ?? '—' }}</td></tr>
        <tr><th>Travel dates</th><td>{{ $booking->travel_date?->format('d M Y') ?? '—' }} @if($booking->return_date) — {{ $booking->return_date->format('d M Y') }} @endif</td></tr>
        <tr><th>Quote ref</th><td>{{ $booking->quotation?->quote_number ?? '—' }}</td></tr>
    </table>
    <h3 class="h6 mt-4">Travelers</h3>
    <table>
        <thead><tr><th>Name</th><th>Type</th></tr></thead>
        <tbody>
        @foreach($booking->travelers as $t)
            <tr><td>{{ $t->first_name }} {{ $t->last_name }}</td><td>{{ $t->traveler_type }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <h3 class="h6 mt-4">Services</h3>
    <table>
        <thead><tr><th>Item</th><th>Description</th></tr></thead>
        <tbody>
        @foreach($booking->items as $item)
            <tr><td>{{ $item->title }}</td><td>{{ $item->description ?? '—' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
