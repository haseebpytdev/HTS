<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My account — '.config('brand.name', 'Hayat Travel Solutions'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body antialiased bg-gray-100 min-h-screen app-ui-shell customer-ui-shell">
    <nav class="customer-top-nav border-bottom">
        <div class="container-xl py-2 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('customer.dashboard') }}" class="text-sm fw-semibold text-brand-navy">My account</a>
                <a href="{{ route('customer.bookings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Bookings</a>
                <a href="{{ route('customer.saved-travelers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Saved travelers</a>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('frontend.home') }}" class="text-sm text-gray-500 hover:text-gray-800">Site home</a>
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800">Log out</button>
                </form>
            </div>
        </div>
    </nav>
    <main class="container-xl py-3 py-md-4">
        @yield('customer-content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
