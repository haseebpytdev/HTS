<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $booking->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; font-size: 13px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 12px; gap: 24px; }
        .brand { font-size: 20px; margin: 0 0 6px; }
        .text-muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        th:last-child, td:last-child { text-align: right; }
        .totals { margin-top: 16px; width: 360px; margin-left: auto; border-collapse: collapse; }
        .totals td { border: none; padding: 4px 0; }
        .totals tr:last-child td { border-top: 1px solid #111827; padding-top: 8px; font-weight: 700; }
        @media print { body { margin: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 14px; text-align: right;">
        <button type="button" onclick="window.print()" style="padding: 8px 12px; border: 1px solid #d1d5db; background: #fff; cursor: pointer;">Print</button>
    </div>
    <div class="row">
        <div>
            <h2 class="brand">{{ config('brand.name') }} — Tax invoice</h2>
            <p class="text-muted mb-0">Invoice structure for accounting; extend with tax IDs and legal footer per territory.</p>
        </div>
        <div>
            <p class="mb-1"><strong>Invoice #</strong> {{ $booking->invoice_number }}</p>
            <p class="mb-1"><strong>Issued</strong> {{ $booking->invoice_issued_at?->format('d M Y H:i') }}</p>
            <p class="mb-1"><strong>Booking #</strong> {{ $booking->booking_number }}</p>
            <p class="mb-0"><strong>Agency</strong> {{ $booking->agency?->name ?? '—' }}</p>
        </div>
    </div>
    <table>
        <tr><td colspan="2"><strong>Bill to</strong></td></tr>
        <tr><td>Name</td><td>{{ $booking->customer_name ?? '—' }}</td></tr>
        <tr><td>Email</td><td>{{ $booking->customer_email ?? '—' }}</td></tr>
    </table>
    <table>
        <thead>
        <tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr>
        </thead>
        <tbody>
        @foreach($booking->items as $item)
            <tr>
                <td>{{ $item->title }} @if($item->description)<br><span class="text-muted">{{ $item->description }}</span>@endif</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                <td>{{ number_format((float) $item->total_price, 2) }} {{ $booking->currency }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $booking->subtotal !== null ? number_format((float) $booking->subtotal, 2) : '—' }} {{ $booking->currency }}</td></tr>
        <tr><td>Tax</td><td>{{ $booking->tax_amount !== null ? number_format((float) $booking->tax_amount, 2) : '0.00' }} {{ $booking->currency }}</td></tr>
        <tr><td>Total due</td><td>{{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</td></tr>
    </table>
    <p class="text-muted" style="margin-top: 24px; font-size: 11px;">Payment status: {{ $booking->payment_status }} · This document was generated from the booking engine (Phase 4).</p>
</body>
</html>
