@extends('layouts.customer')

@section('title', 'Dashboard')

@section('customer-content')
    <div class="content-shell p-4 p-lg-5 mb-4">
        <div class="d-flex flex-wrap items-start justify-content-between gap-3">
        <div>
            <h1 class="as-page-title mb-2">Welcome, {{ $customer->first_name }}</h1>
            <p class="as-body-text mb-0">Manage your flights, payments, and saved travelers from your Hayat Travel Solutions account.</p>
        </div>
        <a href="{{ route('frontend.home') }}" class="btn btn-outline-brand-navy rounded-pill">
            Go to Home Page
        </a>
        </div>
    </div>
    <div class="row g-3">
        <a href="{{ route('customer.bookings.index') }}" class="col-md-4 d-block text-decoration-none">
            <div class="customer-kpi-card h-100">
                <h2 class="as-card-title">Bookings</h2>
                <p class="as-helper-text mt-1 mb-0">History, balances, and traveler details.</p>
            </div>
        </a>
        <a href="{{ route('customer.saved-travelers.index') }}" class="col-md-4 d-block text-decoration-none">
            <div class="customer-kpi-card h-100">
                <h2 class="as-card-title">Saved travelers</h2>
                <p class="as-helper-text mt-1 mb-0">Passenger profiles for faster checkout.</p>
            </div>
        </a>
        <div class="col-md-4">
            <div class="customer-kpi-card h-100">
                <h2 class="as-card-title">Account</h2>
                <p class="as-helper-text mt-1 mb-0">{{ $customer->email }}</p>
            </div>
        </div>
    </div>
@endsection
