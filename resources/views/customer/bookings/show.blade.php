@extends('layouts.customer')

@section('title', 'Booking '.$booking->booking_number)

@section('customer-content')
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="as-page-title mb-1">Booking {{ $booking->booking_number }}</h1>
            <p class="as-helper-text mb-0">{{ str_replace('_', ' ', $booking->status) }} · {{ $booking->agency?->name ?? '—' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('customer.bookings.index') }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">All bookings</a>
            <a href="{{ route('customer.bookings.voucher', $booking) }}" target="_blank" class="btn btn-sm btn-brand-green text-white rounded-pill">Voucher</a>
            <a href="{{ route('customer.bookings.invoice', $booking) }}" target="_blank" class="btn btn-sm btn-outline-brand-navy rounded-pill">Invoice</a>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('error'))
        <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4">
            <div class="content-shell p-4">
                <h2 class="as-card-title mb-3">Trip details</h2>
                <dl class="small mb-0">
                    <dt class="text-secondary">Customer</dt>
                    <dd>{{ $booking->customer_name ?? '—' }}</dd>
                    <dt class="text-secondary pt-2">Travel</dt>
                    <dd>{{ $booking->travel_date?->format('d M Y') ?? '—' }} @if($booking->return_date) — {{ $booking->return_date->format('d M Y') }} @endif</dd>
                </dl>
            </div>

            <div class="content-shell p-4">
                <h2 class="as-card-title mb-3">Services</h2>
                <x-ui.table class="mb-0">
                    <thead><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @foreach($booking->items as $item)
                            <tr>
                                <td>{{ $item->title }}</td>
                                <td class="text-end">{{ number_format((float) $item->total_price, 2) }} {{ $booking->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <p class="text-end fw-semibold mt-3 mb-0">Total {{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</p>
            </div>

            <div class="content-shell p-4">
                <h2 class="as-card-title mb-3">Travelers on booking</h2>
                <ul class="small mb-0">
                    @forelse($booking->travelers as $t)
                        <li>{{ $t->first_name }} {{ $t->last_name }} <span class="text-secondary">({{ $t->traveler_type }})</span></li>
                    @empty
                        <li class="text-secondary">No travelers listed.</li>
                    @endforelse
                </ul>
            </div>

            <div class="content-shell p-4">
                <h2 class="as-card-title mb-3">Linked documents</h2>
                <ul class="small d-grid gap-2 mb-0">
                    @forelse($customerDocuments as $doc)
                        <li class="border rounded-3 px-3 py-2">
                            <div class="fw-semibold">{{ $doc->original_name }}</div>
                            <div class="text-secondary">{{ str_replace('_', ' ', ucfirst($doc->document_type)) }} · v{{ $doc->version_number }} · Approved</div>
                            <a href="{{ route('customer.bookings.documents.download', [$booking, $doc]) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill mt-2">
                                Download
                            </a>
                        </li>
                    @empty
                        <li class="text-secondary">No approved documents shared yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="content-shell p-4">
                <h2 class="as-card-title mb-3">Payments</h2>
                <p class="small mb-1">Paid: <strong>{{ $paymentPanel['paid_total'] }}</strong> {{ $booking->currency }}</p>
                <p class="small mb-4">Balance due: <strong>{{ $paymentPanel['balance_due'] }}</strong> {{ $booking->currency }}</p>

                @if(bccomp($paymentPanel['balance_due'], '0', 2) === 1)
                    <form method="POST" action="{{ route('customer.bookings.payments.deposit', $booking) }}" class="d-grid gap-2 mb-4">
                        @csrf
                        <x-ui.input label="Deposit" type="number" step="0.01" min="0.01" name="amount" required />
                        <x-ui.input type="text" name="idempotency_key" placeholder="Optional idempotency key" />
                        @error('amount')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-brand-green text-white rounded-pill">Pay deposit</button>
                    </form>
                    <form method="POST" action="{{ route('customer.bookings.payments.balance', $booking) }}" class="d-grid gap-2 mb-4">
                        @csrf
                        <x-ui.input label="Partial payment" type="number" step="0.01" min="0.01" name="amount" required />
                        <x-ui.input type="text" name="idempotency_key" placeholder="Optional idempotency key" />
                        @error('amount')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-outline-brand-navy rounded-pill">Pay installment</button>
                    </form>
                    <form method="POST" action="{{ route('customer.bookings.payments.full', $booking) }}" class="d-grid gap-2" onsubmit="return confirm('Pay the full remaining balance?');">
                        @csrf
                        <x-ui.input type="text" name="idempotency_key" placeholder="Optional idempotency key" />
                        @error('idempotency_key')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-outline-dark rounded-pill">Pay full balance</button>
                    </form>
                @else
                    <p class="small text-secondary mb-0">No balance due.</p>
                @endif

                @if($paymentPanel['recent_payments']->isNotEmpty())
                    <h3 class="small fw-semibold text-uppercase text-secondary mt-4 mb-2">Recent payments</h3>
                    <ul class="small text-secondary mb-0">
                        @foreach($paymentPanel['recent_payments'] as $p)
                            <li>#{{ $p->id }} · {{ $p->flow_type }} · {{ $p->status }} · {{ $p->amount }} {{ $p->currency }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
