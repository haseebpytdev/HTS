@extends('layouts.frontend')

@section('title', 'Quote Inquiry')

@section('content')
    @php
        $hasFlightSearch = filled(request('from')) || filled(request('to')) || filled(request('departure_date'));
    @endphp
    <x-ui.public-page-hero
        title="Request a custom quote"
        subtitle="Share your route, dates, and cabin preference. Our consultants will respond with options that match your budget and schedule."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Quote inquiry', 'url' => null],
        ]"
    />

    @if($hasFlightSearch)
        <x-ui.public-content-section class="pb-3">
                <x-ui.form-section-card title="Flight Filters" subtitle="Refine supplier behavior for this quote request.">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="small text-muted">
                            Route: {{ request('from', 'Any') }} → {{ request('to', 'Any') }}
                        </div>
                    </div>

                    <form method="GET" action="{{ route('frontend.inquiries.quote') }}" class="row g-3 align-items-end">
                        <input type="hidden" name="from" value="{{ request('from') }}">
                        <input type="hidden" name="to" value="{{ request('to') }}">
                        <input type="hidden" name="departure_date" value="{{ request('departure_date') }}">
                        <input type="hidden" name="trip_type" value="{{ request('trip_type', 'one_way') }}">
                        <input type="hidden" name="passengers" value="{{ request('passengers', '2') }}">
                        <input type="hidden" name="cabin_class" value="{{ request('cabin_class', 'economy') }}">

                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="filterBranded" name="branded_search" value="1" @checked(request('branded_search') === '1')>
                                <label class="form-check-label" for="filterBranded">Branded Search</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="filterNdc" name="ndc_fare" value="1" @checked(request('ndc_fare') === '1')>
                                <label class="form-check-label" for="filterNdc">NDC Fare</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="filterLongConnection" name="long_connection" value="1" @checked(request('long_connection') === '1')>
                                <label class="form-check-label" for="filterLongConnection">Long Connection</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="filterDirectFlight" name="direct_flight" value="1" @checked(request('direct_flight', '1') === '1')>
                                <label class="form-check-label" for="filterDirectFlight">Direct Flight</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <x-ui.button variant="secondary" pill="true" type="submit">Apply Filters</x-ui.button>
                            <a href="{{ route('frontend.inquiries.quote', request()->only(['from', 'to', 'departure_date', 'trip_type', 'passengers', 'cabin_class'])) }}" class="btn btn-link text-decoration-none">Reset filters</a>
                        </div>
                    </form>
                </x-ui.form-section-card>
        </x-ui.public-content-section>
    @endif

    <x-ui.public-content-section>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-8 col-xl-7">
                    <x-ui.quote-request-card title="Request your itinerary quote" subtitle="Complete the form below. Our consultants will follow up with suitable fare and package options.">
                        <form id="quote-inquiry-form" method="POST" action="{{ route('frontend.inquiries.store-quote') }}">
                            @csrf
                            <x-forms.inquiry-form-fields />
                            <x-ui.button variant="primary" pill="true" size="lg" type="submit" class="mt-3">Submit quote inquiry</x-ui.button>
                        </form>
                    </x-ui.quote-request-card>
                </div>
                <div class="col-lg-4 col-xl-3">
                    <x-ui.form-section-card title="Need faster help?" subtitle="For urgent departures, connect with our team directly.">
                        <div class="d-grid gap-2">
                            <a href="{{ route('frontend.contact') }}" class="btn btn-outline-brand-navy rounded-pill">Contact support</a>
                            <a href="{{ route('frontend.flights.search') }}" class="btn btn-outline-brand-navy rounded-pill">Search flights</a>
                        </div>
                    </x-ui.form-section-card>
                </div>
            </div>
    </x-ui.public-content-section>
    <div class="d-md-none position-fixed start-0 end-0 bottom-0 px-3 pb-3" style="z-index:1050; margin-bottom:72px;">
        <button type="submit" form="quote-inquiry-form" class="btn btn-brand-green text-white w-100 rounded-pill shadow">
            Submit quote inquiry
        </button>
    </div>
@endsection
