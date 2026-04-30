<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#d35400">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/pwa/icon-192.svg') }}">

    <title>@yield('title', config('brand.name', 'Hayat Travel Solutions')) — {{ config('app.name', 'Travel') }}</title>

    @stack('head')

    @vite(['resources/css/public-ui.css'])

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/frontend.css') }}">

    @stack('styles')
</head>
<body class="frontend-site d-flex flex-column min-vh-100">
    @php
        $umrahPackagesEnabled = (bool) data_get($frontendFeatureFlags ?? [], 'umrah_packages_enabled', true);
    @endphp
    <x-frontend.main-nav />

    <main class="flex-grow-1">
        @yield('content')
    </main>

    <x-frontend.site-footer />
    <nav class="mobile-action-bar d-lg-none">
        <a href="{{ route('frontend.home') }}" class="mobile-action-bar__item {{ request()->routeIs('frontend.home') ? 'active' : '' }}"><i class="bi bi-house-door"></i><span>Home</span></a>
        @if($umrahPackagesEnabled)
            <a href="{{ route('frontend.packages.index') }}" class="mobile-action-bar__item {{ request()->routeIs('frontend.packages.*') ? 'active' : '' }}"><i class="bi bi-suitcase2"></i><span>Packages</span></a>
        @endif
        <a href="{{ route('frontend.inquiries.quote') }}" class="mobile-action-bar__item {{ request()->routeIs('frontend.inquiries.*') ? 'active' : '' }}"><i class="bi bi-chat-dots"></i><span>Quote</span></a>
        <a href="{{ route('frontend.contact') }}" class="mobile-action-bar__item {{ request()->routeIs('frontend.contact') ? 'active' : '' }}"><i class="bi bi-telephone"></i><span>Contact</span></a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", function () {
                navigator.serviceWorker.register("{{ asset('sw.js') }}").catch(function () {});
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
