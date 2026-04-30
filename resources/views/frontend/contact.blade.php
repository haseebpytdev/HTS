@extends('layouts.frontend')

@section('title', 'Contact Us')

@section('content')
    @php
        $umrahPackagesEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'umrah_packages_enabled', true);
        $contact = (array) data_get($cmsSettings ?? [], 'contact_page', []);
    @endphp
    <x-frontend.page-hero
        :title="data_get($contact, 'title', 'Contact Us')"
        :subtitle="data_get($contact, 'subtitle', 'Reach our Lahore team for flights, group tickets, Umrah packages, or general support. We respond as quickly as we can during business hours.')"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Contact Us', 'url' => null],
        ]"
    />

    <section class="section-block">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="content-shell p-4 p-lg-5 h-100">
                        <h2 class="h5 text-brand-navy fw-bold mb-4">Get in touch</h2>
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex gap-3 mb-4">
                                <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(13,138,91,0.12);">
                                    <i class="bi bi-geo-alt-fill text-brand-green fs-5"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-brand-navy">Office</div>
                                    <p class="small text-secondary mb-0">{{ data_get($contact, 'office', 'Lahore, Pakistan') }}</p>
                                </div>
                            </li>
                            <li class="d-flex gap-3 mb-4">
                                <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(13,138,91,0.12);">
                                    <i class="bi bi-telephone-fill text-brand-green fs-5"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-brand-navy">Phone</div>
                                    <a href="tel:{{ preg_replace('/\s+/', '', data_get($contact, 'phone', '+92 300 0000000')) }}" class="small text-decoration-none text-brand-green">{{ data_get($contact, 'phone', '+92 300 0000000') }}</a>
                                </div>
                            </li>
                            <li class="d-flex gap-3 mb-4">
                                <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(13,138,91,0.12);">
                                    <i class="bi bi-envelope-fill text-brand-green fs-5"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-brand-navy">Email</div>
                                    <a href="mailto:{{ data_get($contact, 'email', config('brand.support_email')) }}" class="small text-decoration-none text-brand-green">{{ data_get($contact, 'email', config('brand.support_email')) }}</a>
                                </div>
                            </li>
                            <li class="d-flex gap-3">
                                <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(13,138,91,0.12);">
                                    <i class="bi bi-whatsapp text-brand-green fs-5"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-brand-navy">WhatsApp</div>
                                    <p class="small text-secondary mb-0">{{ data_get($contact, 'whatsapp_hint', 'Message us for quick fare checks and group seat holds.') }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="content-shell p-4 p-lg-5 h-100">
                        <h2 class="h5 text-brand-navy fw-bold mb-2">{{ data_get($contact, 'hours_heading', 'Business hours') }}</h2>
                        <p class="text-secondary small mb-4">{{ data_get($contact, 'hours_subtitle', 'Timings may vary on public holidays. For urgent departures, call or WhatsApp and mention your travel date.') }}</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                    <div class="fw-semibold text-brand-navy mb-1">{{ data_get($contact, 'weekday_label', 'Monday – Saturday') }}</div>
                                    <p class="small text-secondary mb-0">{{ data_get($contact, 'weekday_hours', '10:00 – 19:00 (PKT)') }}</p>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                    <div class="fw-semibold text-brand-navy mb-1">{{ data_get($contact, 'sunday_label', 'Sunday') }}</div>
                                    <p class="small text-secondary mb-0">{{ data_get($contact, 'sunday_hours', 'Limited desk — WhatsApp preferred') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-top">
                            <h3 class="h6 text-brand-navy fw-bold mb-2">{{ data_get($contact, 'start_online_heading', 'Start online') }}</h3>
                            <p class="small text-secondary mb-3">{{ data_get($contact, 'start_online_text', 'For flight quotes and package inquiries, use our forms so we have your route and dates on file.') }}</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('frontend.inquiries.quote') }}" class="btn btn-brand-green text-white rounded-pill px-4">{{ data_get($contact, 'quote_cta_text', 'Quote inquiry') }}</a>
                                @if($umrahPackagesEnabled)
                                    <a href="{{ route('frontend.packages.index') }}" class="btn btn-outline-brand-navy rounded-pill px-4">{{ data_get($contact, 'package_cta_text', 'Browse packages') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
