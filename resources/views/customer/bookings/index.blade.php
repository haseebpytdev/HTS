@extends('layouts.customer')

@section('title', 'My bookings')

@section('customer-content')
    <x-ui.section-header
        title="My bookings"
        subtitle="Track status, view totals, and open booking details from your customer workspace."
    />
    @if($bookings->isEmpty())
        <x-ui.empty-state
            title="No bookings linked yet"
            message="When your agency assigns a booking to your profile, it will appear here."
            icon="bi-journal-check"
        />
    @else
        <div class="content-shell p-3 p-md-4">
            <x-ui.table class="mb-0">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $b)
                        <tr>
                            <td>{{ $b->booking_number }}</td>
                            <td><x-ui.badge tone="muted">{{ str_replace('_', ' ', $b->status) }}</x-ui.badge></td>
                            <td class="text-end">{{ number_format((float) $b->total_amount, 2) }} {{ $b->currency }}</td>
                            <td class="text-end">
                                <a href="{{ route('customer.bookings.show', $b) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
        <x-ui.pagination :paginator="$bookings" />
    @endif
@endsection
