@extends('layouts.frontend')

@section('title', 'Hayat Travel Solutions — Flights, Groups & Umrah')

@section('content')
    @php
        $heroBg = ! empty($hero['background_image'] ?? null)
            ? \App\Support\Media::url($hero['background_image'])
            : asset('assets/images/banners/banner2.png');
    @endphp

    @php
        $groupTicketingEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'group_ticketing_enabled', true);
        $umrahPackagesEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'umrah_packages_enabled', true);
        $showHero = (bool) data_get($cmsSettings ?? [], 'homepage.show_hero', true);
        $showWhyUs = (bool) data_get($cmsSettings ?? [], 'homepage.show_why_us', true);
        $showGroups = (bool) data_get($cmsSettings ?? [], 'homepage.show_group_tickets', true);
        $showDeals = (bool) data_get($cmsSettings ?? [], 'homepage.show_deals', true);
        $showFeaturedGroups = (bool) data_get($cmsSettings ?? [], 'featured.show_featured_groups', true);
        $showFeaturedPackages = (bool) data_get($cmsSettings ?? [], 'featured.show_featured_packages', true);
        $promoBannerText = (string) data_get($cmsSettings ?? [], 'homepage.promo_banner_text', '');
        $promoBannerLink = (string) data_get($cmsSettings ?? [], 'homepage.promo_banner_link', '');
    @endphp

    @if($promoBannerText !== '')
        <section class="bg-warning-subtle border-bottom">
            <div class="container py-2 small fw-semibold text-dark">
                @if($promoBannerLink !== '')
                    <a href="{{ $promoBannerLink }}" class="text-reset text-decoration-none">{{ $promoBannerText }}</a>
                @else
                    {{ $promoBannerText }}
                @endif
            </div>
        </section>
    @endif

    @if($showHero)
    <header class="hero-full hero-full--immersive">
        <div class="hero-full__bg">
            <img src="{{ $heroBg }}" alt="Travel" class="hero-full__media" id="heroBackdropMedia">
            <div class="hero-full__overlay"></div>
            <div class="container hero-full__content-wrap">
                <div class="row align-items-center">
                    <div class="col-lg-8 mb-4 mb-lg-0">
                        <x-ui.badge tone="info" class="mb-3">Trusted Travel & Ticketing Partner</x-ui.badge>
                        <h1 class="hero-full__headline text-white fw-bold mb-3">Plan Smarter Flights,<br>Group Travel &amp;<br>Umrah Journeys</h1>
                        <p class="hero-full__lead text-white-50 mb-4">Compare live fares, manage group departures, and book with expert support from Hayat Travel Solutions.</p>
                        <div class="d-flex flex-wrap gap-3 hero-cta-group">
                            <a href="{{ route('frontend.flights.search') }}" class="btn btn-brand-green rounded-pill px-4 py-2 text-white">Book a Flight</a>
                            <a href="{{ route('frontend.groups.index') }}" class="btn btn-outline-light rounded-pill px-4 py-2">Browse Group Tickets</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container hero-search-float-wrap">
            <div class="hero-search-float">
                <x-forms.hero-search-panel />
                <button id="pwa-install-btn" type="button" class="btn btn-sm btn-light rounded-pill mt-3 d-none">
                    <i class="bi bi-phone me-1"></i> Install app
                </button>
            </div>
        </div>
    </header>
    @endif

    <section class="section-block section-block--layer pt-0">
        <div class="container">
            <div class="row g-3 g-lg-4 mt-0 mt-lg-2">
                <div class="col-6 col-lg-3">
                    <x-ui.stats-card value="24/7" label="Support" icon="bi-headset" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-ui.stats-card value="Expert" label="Group Travel Team" icon="bi-people-fill" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-ui.stats-card value="Live" label="Flight Search" icon="bi-airplane-engines-fill" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-ui.stats-card value="Trusted" label="Umrah Planning" icon="bi-moon-stars-fill" />
                </div>
            </div>
        </div>
    </section>

    @if($showWhyUs)
    <section class="section-block section-block--muted">
        <div class="container">
            <x-ui.section-heading
                kicker="Services"
                title="{{ $whyUs['section_title'] ?? 'Why Book With Us?' }}"
                subtitle="{{ $whyUs['section_subtitle'] ?? 'Real people, clear pricing, and decades of experience serving travellers.' }}"
                align="center"
                class="mb-5"
            />
            <div class="row g-4">
                @foreach ($whyUs['items'] ?? [] as $item)
                    <div class="col-sm-6 col-xl-3">
                        <article class="why-card h-100">
                            <div class="why-card__icon">
                                <i class="bi {{ $item['icon'] ?? 'bi-star-fill' }}"></i>
                            </div>
                            <h3 class="why-card__title h6 fw-bold">{{ $item['title'] ?? '' }}</h3>
                            <p class="why-card__text small text-secondary mb-0">{{ $item['description'] ?? '' }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if($groupTicketingEnabled && $showGroups && $showFeaturedGroups)
    <section class="section-block section-block--layer-dark">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-md-between gap-3 mb-4">
                <div>
                    <h2 class="as-section-title mb-1">{{ $groupTickets['section_title'] ?? 'Explore Group Tickets' }}</h2>
                    <p class="section-subtitle mb-0">Popular corridors and Umrah group departures.</p>
                </div>
                <a href="{{ route('frontend.groups.index') }}" class="btn btn-outline-brand-navy rounded-pill px-4 align-self-start">View all groups</a>
            </div>

            @php
                $gtItems = collect($groupTickets['items'] ?? []);
                $gtChunks = $gtItems->chunk(4);
            @endphp

            <div id="carouselGroupTickets" class="carousel slide carousel-fade" data-bs-ride="carousel">
                <div class="carousel-inner rounded-4 overflow-hidden">
                    @forelse ($gtChunks as $chunk)
                        <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                            <div class="row g-3 g-md-4 p-1">
                                @foreach ($chunk as $item)
                                    <div class="col-6 col-md-3">
                                        <x-ui.destination-card
                                            :title="$item['title'] ?? 'Group Journey'"
                                            :subtitle="$item['subtitle'] ?? 'Seats filling fast'"
                                            :badge="$item['tag'] ?? 'Group Tickets'"
                                            :href="! empty($item['link'] ?? null) ? $item['link'] : route('frontend.groups.index')"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="carousel-item active">
                            <p class="text-muted text-center py-5 mb-0">No group highlights configured yet.</p>
                        </div>
                    @endforelse
                </div>
                @if($gtChunks->count() > 1)
                    <div class="d-flex justify-content-center align-items-center gap-3 mt-4">
                        <button class="carousel-control-prev carousel-control-btn position-static" type="button" data-bs-target="#carouselGroupTickets" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <div class="carousel-indicators position-static m-0">
                            @for ($i = 0; $i < $gtChunks->count(); $i++)
                                <button type="button" data-bs-target="#carouselGroupTickets" data-bs-slide-to="{{ $i }}" class="{{ $i === 0 ? 'active' : '' }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}" aria-label="Slide {{ $i + 1 }}"></button>
                            @endfor
                        </div>
                        <button class="carousel-control-next carousel-control-btn position-static" type="button" data-bs-target="#carouselGroupTickets" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    @if($umrahPackagesEnabled && $showDeals && $showFeaturedPackages)
    <section class="section-block section-block--muted section-block--layer">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="as-section-title mb-2">{{ $deals['section_title'] ?? 'Our Deals & Offers' }}</h2>
                <p class="section-subtitle mx-auto mb-0 col-lg-8">Limited-time fares and package promotions — confirm with our team before booking.</p>
            </div>
            <div class="row g-4">
                @foreach ($deals['items'] ?? [] as $item)
                    <div class="col-6 col-lg-3">
                        <x-cards.package-card
                            :title="$item['title'] ?? ''"
                            :subtitle="$item['subtitle'] ?? ''"
                            :price="$item['price'] ?? null"
                            :href="! empty($item['link'] ?? null) ? $item['link'] : route('frontend.packages.index')"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="section-block">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-6">
                    <div class="trust-panel h-100">
                        <p class="trust-panel__kicker mb-2">Traveler reviews</p>
                        <h2 class="as-section-title mb-2">Trusted by families, agencies, and business travelers</h2>
                        <p class="as-body-text mb-4">Every itinerary is reviewed by a consultant before ticketing, with transparent follow-up from quote to confirmation.</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <article class="review-card h-100">
                                    <p class="mb-3">"The fare options were clear and the team managed our multi-city booking with zero confusion."</p>
                                    <div class="small fw-semibold">Corporate traveler</div>
                                </article>
                            </div>
                            <div class="col-sm-6">
                                <article class="review-card h-100">
                                    <p class="mb-3">"Our Umrah group seats and hotel details were handled professionally from day one."</p>
                                    <div class="small fw-semibold">Group coordinator</div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                        <div class="gallery-grid h-100">
                            <div class="gallery-tile gallery-tile--large" style="background-image: linear-gradient(135deg, rgba(13,138,91,0.55), rgba(15,42,68,0.85)), url('{{ $heroBg }}'); background-size: cover; background-position: center;">
                            <span>Premium routes</span>
                        </div>
                        <div class="gallery-tile"><span>Group departures</span></div>
                        <div class="gallery-tile"><span>Umrah planning</span></div>
                        <div class="gallery-tile"><span>24/7 support</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-block section-block--muted">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <h2 class="as-section-title mb-1">Travel tips and planning guides</h2>
                    <p class="section-subtitle mb-0">Actionable guidance from our operations team to help travelers plan smarter.</p>
                </div>
                <a href="{{ route('frontend.blog.index') }}" class="btn btn-outline-brand-navy rounded-pill px-4">Read more insights</a>
            </div>
            <div class="row g-3 g-lg-4">
                <div class="col-md-4">
                    <article class="tip-card h-100">
                        <h3 class="as-card-title mb-2">How to compare flight offers correctly</h3>
                        <p class="as-body-text mb-0">Focus on total travel time, baggage policy, and revalidation terms instead of fare only.</p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="tip-card h-100">
                        <h3 class="as-card-title mb-2">When to lock group seats early</h3>
                        <p class="as-body-text mb-0">High-demand corridors often close inventory fast. Shortlist travelers and confirm intent early.</p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="tip-card h-100">
                        <h3 class="as-card-title mb-2">Prepare for smooth Umrah departures</h3>
                        <p class="as-body-text mb-0">Keep traveler documents, visa milestones, and payment proofs organized before final ticketing.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
    @push('scripts')
        <script>
            (function () {
                const nav = document.getElementById('mainSiteNav');
                const img = document.getElementById('heroBackdropMedia');
                if (!nav || !img || !nav.classList.contains('frontend-nav--transparent')) {
                    return;
                }

                function applyNavContrast() {
                    try {
                        const w = 120;
                        const h = 48;
                        const canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        if (!ctx || !img.naturalWidth) {
                            return;
                        }

                        ctx.drawImage(img, 0, 0, w, h);
                        const data = ctx.getImageData(0, 0, w, h).data;
                        let sum = 0;
                        let count = 0;
                        for (let i = 0; i < data.length; i += 16) {
                            const r = data[i];
                            const g = data[i + 1];
                            const b = data[i + 2];
                            sum += 0.2126 * r + 0.7152 * g + 0.0722 * b;
                            count++;
                        }
                        const avg = sum / count / 255;
                        const overlayFactor = 0.55;
                        const effective = avg * (1 - overlayFactor) + overlayFactor * 0.12;
                        const useLightNavText = effective > 0.5;

                        nav.classList.toggle('frontend-nav--on-light', useLightNavText);
                        nav.classList.toggle('frontend-nav--on-dark', !useLightNavText);
                        nav.classList.toggle('navbar-light', useLightNavText);
                        nav.classList.toggle('navbar-dark', !useLightNavText);
                    } catch {
                        nav.classList.add('frontend-nav--on-dark', 'navbar-dark');
                        nav.classList.remove('frontend-nav--on-light', 'navbar-light');
                    }
                }

                if (img.complete && img.naturalWidth) {
                    applyNavContrast();
                } else {
                    img.addEventListener('load', applyNavContrast, { once: true });
                }
            })();
        </script>
        <script>
            (function () {
                let deferredPrompt = null;
                const installBtn = document.getElementById('pwa-install-btn');
                if (!installBtn) return;

                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    installBtn.classList.remove('d-none');
                });

                installBtn.addEventListener('click', async function () {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    await deferredPrompt.userChoice;
                    deferredPrompt = null;
                    installBtn.classList.add('d-none');
                });
            })();
        </script>
    @endpush
@endsection
