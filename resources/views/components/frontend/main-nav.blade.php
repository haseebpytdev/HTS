@props([
    'transparent' => false,
])

@php
    $cms = app(\App\Services\Content\ContentSettingsService::class)->frontendSettings();
    $groupTicketingEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'group_ticketing_enabled', true);
    $umrahPackagesEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'umrah_packages_enabled', true);
    $isHomeRoute = request()->routeIs('frontend.home');
    $transparent = $transparent || $isHomeRoute;
    $navClass = $transparent
        ? 'frontend-nav frontend-nav--transparent frontend-nav--on-dark navbar-dark'
        : 'frontend-nav frontend-nav--solid navbar-light bg-white shadow-sm';
    $isWebLoggedIn = auth('web')->check();
    $isCustomerLoggedIn = auth('customer')->check();
@endphp

<nav id="mainSiteNav" class="navbar navbar-expand-lg align-items-lg-center {{ $navClass }} sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center py-1 me-3 text-decoration-none" href="{{ route('frontend.home') }}" aria-label="{{ config('brand.name', 'Hayat Travel Solutions') }} — Home">
            <x-frontend.brand-wordmark />
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#frontendMainNav" aria-controls="frontendMainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="frontendMainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link px-lg-4 {{ request()->routeIs('frontend.home') ? 'active fw-semibold' : 'fw-medium' }}" href="{{ route('frontend.home') }}">Home</a>
                </li>
                @if($groupTicketingEnabled || $umrahPackagesEnabled)
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-lg-4 fw-medium" href="#" id="navServices" role="button" data-bs-toggle="dropdown" aria-expanded="false">Our Services</a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 py-2" aria-labelledby="navServices">
                            <li><a class="dropdown-item rounded-2" href="{{ route('frontend.inquiries.quote') }}"><i class="bi bi-search me-2 text-brand-green"></i>Request a quote</a></li>
                            @if($umrahPackagesEnabled)
                                <li><a class="dropdown-item rounded-2" href="{{ route('frontend.packages.index') }}"><i class="bi bi-suitcase2 me-2 text-brand-green"></i>Umrah packages</a></li>
                            @endif
                            @if($groupTicketingEnabled)
                                <li><a class="dropdown-item rounded-2" href="{{ route('frontend.groups.index') }}"><i class="bi bi-people me-2 text-brand-green"></i>Group travel</a></li>
                            @endif
                        </ul>
                    </li>
                @endif
                <li class="nav-item">
                    @if((bool) data_get($cms, 'top_nav.show_bank_link', true))
                        <a class="nav-link px-lg-4 {{ request()->routeIs('frontend.bank') ? 'active fw-semibold' : 'fw-medium' }}" href="{{ route('frontend.bank') }}">Bank Details</a>
                    @endif
                </li>
                <li class="nav-item">
                    @if((bool) data_get($cms, 'top_nav.show_about_link', true))
                        <a class="nav-link px-lg-4 {{ request()->routeIs('frontend.about') ? 'active fw-semibold' : 'fw-medium' }}" href="{{ route('frontend.about') }}">About Us</a>
                    @endif
                </li>
                <li class="nav-item">
                    @if((bool) data_get($cms, 'top_nav.show_contact_link', true))
                        <a class="nav-link px-lg-4 {{ request()->routeIs('frontend.contact') ? 'active fw-semibold' : 'fw-medium' }}" href="{{ route('frontend.contact') }}">Contact Us</a>
                    @endif
                </li>
                <li class="nav-item">
                    @if((bool) data_get($cms, 'top_nav.show_blog_link', true) && Route::has('frontend.blog.index'))
                        <a class="nav-link px-lg-4 {{ request()->routeIs('frontend.blog.*') ? 'active fw-semibold' : 'fw-medium' }}" href="{{ route('frontend.blog.index') }}">Blog</a>
                    @endif
                </li>
            </ul>
            <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-3 mt-3 mt-lg-0 pt-2 pt-lg-0 nav-cta-wrap">
                @if (! $isWebLoggedIn && ! $isCustomerLoggedIn)
                    <a href="{{ route('customer.register') }}" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">Sign up</a>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-brand-green rounded-pill px-3 text-white">Login</a>
                @elseif ($isCustomerLoggedIn)
                    <a href="{{ route('customer.dashboard') }}" class="btn btn-sm btn-brand-green rounded-pill px-3 text-white">My Account</a>
                    <form method="POST" action="{{ route('customer.logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">Logout</button>
                    </form>
                @else
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-brand-green rounded-pill px-3 text-white">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-brand-navy rounded-pill px-3">Logout</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</nav>

@once
    @push('scripts')
        <script>
            (function () {
                const nav = document.getElementById('mainSiteNav');
                if (!nav) {
                    return;
                }

                const SCROLL_THRESHOLD = 12;
                const applyNavScrollState = function () {
                    nav.classList.toggle('frontend-nav--scrolled', window.scrollY > SCROLL_THRESHOLD);
                };

                applyNavScrollState();
                window.addEventListener('scroll', applyNavScrollState, { passive: true });
                window.addEventListener('resize', applyNavScrollState);
            })();
        </script>
    @endpush
@endonce
