@extends('layouts.frontend')

@section('title', 'Review your flight')

@section('content')
    <x-frontend.page-hero
        title="Review your flight"
        subtitle="We have confirmed the latest fare with the airline. Check the itinerary and price, then continue to traveler details."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Flights', 'url' => route('frontend.flights.search')],
            ['label' => 'Review', 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            @if(session('flight_status'))
                <div class="alert alert-success">{{ session('flight_status') }}</div>
            @endif

            @if(session('flight_error'))
                <div class="alert alert-danger">{{ session('flight_error') }}</div>
            @endif

            @php
                $offer = is_array($selection['offer'] ?? null) ? $selection['offer'] : [];
                $displayPrice = is_array($offer['display_price'] ?? null) ? $offer['display_price'] : null;
                $revalidation = is_array($selection['revalidation'] ?? null) ? $selection['revalidation'] : [];
                $fareComparison = is_array($selection['fare_comparison'] ?? null) ? $selection['fare_comparison'] : null;
            @endphp

            <div class="content-shell booking-flow-shell p-3 p-md-4 p-lg-5">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <x-ui.badge tone="success">Step 1 of 3</x-ui.badge>
                    <span class="as-helper-text">Review confirmed fare before entering traveler details.</span>
                </div>
                @include('frontend.flights.partials.flight-itinerary-summary', [
                    'offer' => $offer,
                    'provider' => $selection['provider'] ?? '',
                    'fareComparison' => $fareComparison,
                ])

                @if($displayPrice !== null)
                    <div class="border rounded-3 p-3 bg-light my-4">
                        <div class="text-secondary text-uppercase fw-semibold small">Confirmed fare</div>
                        <div class="fw-bold fs-3">
                            <span class="text-uppercase">{{ $displayPrice['display_currency'] ?? '' }}</span>
                            @if(! empty($displayPrice['display_amount_unavailable']))
                                <span class="text-body-secondary">—</span>
                            @else
                                {{ $displayPrice['display_formatted'] ?? '' }}
                            @endif
                        </div>
                        @if(! empty($displayPrice['display_amount_unavailable']))
                            <div class="small text-secondary mt-1">Display conversion unavailable; amount was confirmed with the airline in the booking step.</div>
                        @endif
                        <div class="as-helper-text mt-1">Per traveler</div>
                    </div>
                @endif

                @if($revalidation !== [])
                    <div class="as-helper-text mb-4">
                        Fare checked at {{ $revalidation['revalidated_at'] ?? 'N/A' }}.
                        Reference: <code>{{ $revalidation['correlation_id'] ?? ($selection['correlation_id'] ?? '') }}</code>
                    </div>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('frontend.flights.search') }}" class="btn btn-outline-secondary">New search</a>
                    <form method="POST" action="{{ route('frontend.flights.booking.continue') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-brand-green rounded-3" type="submit">Continue to traveler details</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
