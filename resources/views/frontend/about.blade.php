@extends('layouts.frontend')

@section('title', 'About Us')

@section('content')
    @php($about = (array) data_get($cmsSettings ?? [], 'about_page', []))
    <x-frontend.page-hero
        :title="data_get($about, 'title', 'About '.config('brand.name'))"
        :subtitle="data_get($about, 'subtitle', 'We help travellers book smarter: competitive fares, transparent group ticketing, and curated Umrah packages — backed by a Lahore-based team you can reach by phone or WhatsApp.')"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'About Us', 'url' => null],
        ]"
    />

    <section class="section-block">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-7">
                    <div class="content-shell p-4 p-lg-5">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">{{ data_get($about, 'who_we_are_heading', 'Who we are') }}</h2>
                        <p class="text-secondary mb-3">{{ data_get($about, 'who_we_are_body', config('brand.name').' is built for Pakistani travellers who want clear pricing, honest advice, and reliable support for flights, groups, and religious travel. Our consultants combine years of airline and tour experience with tools that keep your options organised.') }}</p>
                        <p class="text-secondary mb-0">{{ data_get($about, 'who_we_are_body_2', 'Whether you need a single sector, a multi-city itinerary, or seats on a departing group, we focus on what matters: correct fares, timely ticketing, and communication you can trust.') }}</p>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="content-shell p-4 p-lg-5 h-100">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">{{ data_get($about, 'why_heading', 'Why travellers choose us') }}</h2>
                        <ul class="list-unstyled small text-secondary mb-4">
                            <li class="d-flex gap-2 mb-2"><i class="bi bi-check-circle-fill text-brand-green mt-1"></i><span>{{ data_get($about, 'highlight_1', 'Central Lahore presence and responsive customer care') }}</span></li>
                            <li class="d-flex gap-2 mb-2"><i class="bi bi-check-circle-fill text-brand-green mt-1"></i><span>{{ data_get($about, 'highlight_2', 'Group departures and Umrah packages in one place') }}</span></li>
                            <li class="d-flex gap-2 mb-2"><i class="bi bi-check-circle-fill text-brand-green mt-1"></i><span>{{ data_get($about, 'highlight_3', 'Straightforward bank transfer details and booking follow-up') }}</span></li>
                            <li class="d-flex gap-2"><i class="bi bi-check-circle-fill text-brand-green mt-1"></i><span>{{ data_get($about, 'highlight_4', 'Quote and inquiry flows designed for your itinerary, not generic forms') }}</span></li>
                        </ul>
                        <a href="{{ route('frontend.contact') }}" class="btn btn-brand-green text-white rounded-pill w-100 w-sm-auto px-4">{{ data_get($about, 'cta_text', 'Contact our team') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
