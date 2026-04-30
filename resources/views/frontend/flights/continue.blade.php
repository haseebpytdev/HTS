@extends('layouts.frontend')

@section('title', 'Continue Flight Booking')

@section('content')
    <x-frontend.page-hero
        title="Continue flight booking"
        subtitle="Enter traveler details for the confirmed fare, then submit your booking request."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Flights', 'url' => route('frontend.flights.search')],
            ['label' => 'Review', 'url' => route('frontend.flights.booking.review')],
            ['label' => 'Booking', 'url' => null],
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
                $booking = is_array($bookingResult ?? null) ? $bookingResult : null;
            @endphp

            <div class="row g-4">
                <div class="col-12 col-lg-5">
                    <div class="content-shell booking-flow-shell p-3 p-md-4 mb-4">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <x-ui.badge tone="primary">Step 2 of 3</x-ui.badge>
                            <span class="as-helper-text">Traveler and contact details are required for booking submission.</span>
                        </div>
                        @include('frontend.flights.partials.flight-itinerary-summary', [
                            'offer' => $offer,
                            'provider' => $selection['provider'] ?? '',
                            'fareComparison' => null,
                        ])

                        @if($displayPrice !== null)
                            <div class="border rounded-3 p-3 bg-light mb-3">
                                <div class="text-secondary text-uppercase fw-semibold small">Confirmed fare</div>
                                <div class="fw-bold fs-3">
                                    <span class="text-uppercase">{{ $displayPrice['display_currency'] ?? '' }}</span>
                                    @if(! empty($displayPrice['display_amount_unavailable']))
                                        <span class="text-body-secondary">—</span>
                                    @else
                                        {{ $displayPrice['display_formatted'] ?? '' }}
                                    @endif
                                </div>
                                <div class="as-helper-text mt-1">Per traveler</div>
                            </div>
                        @endif

                        @if($revalidation !== [])
                            <div class="as-helper-text mb-3">
                                Fare checked at {{ $revalidation['revalidated_at'] ?? 'N/A' }}
                            </div>
                        @endif

                        @if($booking !== null)
                            <div class="alert alert-success mb-0">
                                <div class="fw-semibold">Booking created</div>
                                <div>Reference: <code>{{ $booking['booking_reference'] ?? 'N/A' }}</code></div>
                                @if(! empty($booking['pnr']))
                                    <div>PNR: <code>{{ $booking['pnr'] }}</code></div>
                                @endif
                                <div class="small mt-1 text-uppercase">{{ $booking['status'] ?? 'pending' }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="content-shell booking-flow-shell p-3 p-md-4">
                        <h2 class="as-section-title mb-3">Traveler details</h2>
                        <form method="POST" action="{{ route('frontend.flights.book') }}">
                            @csrf
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contact email</label>
                                    <input type="email" name="contact_email" class="form-control as-input" value="{{ old('contact_email') }}">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contact phone</label>
                                    <input type="text" name="contact_phone" class="form-control as-input" value="{{ old('contact_phone') }}">
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                @foreach($travelerForms as $index => $traveler)
                                    <div class="border rounded-3 p-3">
                                        <div class="fw-semibold mb-3">{{ $traveler['label'] }}</div>
                                        <input type="hidden" name="travelers[{{ $index }}][traveler_type]" value="{{ $traveler['traveler_type'] }}">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Given name</label>
                                            <input type="text" name="travelers[{{ $index }}][given_name]" class="form-control as-input" value="{{ old('travelers.'.$index.'.given_name') }}" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Family name</label>
                                            <input type="text" name="travelers[{{ $index }}][family_name]" class="form-control as-input" value="{{ old('travelers.'.$index.'.family_name') }}" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Date of birth</label>
                                            <input type="date" name="travelers[{{ $index }}][date_of_birth]" class="form-control as-input" value="{{ old('travelers.'.$index.'.date_of_birth') }}">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Nationality</label>
                                            <input type="text" name="travelers[{{ $index }}][nationality]" class="form-control as-input text-uppercase" maxlength="2" value="{{ old('travelers.'.$index.'.nationality') }}">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if($errors->any())
                                <div class="alert alert-warning mt-3 mb-0">
                                    <div class="fw-semibold mb-1">Please correct the traveler details:</div>
                                    <ul class="mb-0 ps-3">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a href="{{ route('frontend.flights.booking.review') }}" class="btn btn-outline-secondary">Back to review</a>
                                <button class="btn btn-brand-green rounded-3" type="submit">Book now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
