@extends('layouts.frontend')

@section('title', 'ApnaSafar - Home')

@section('content')
    <header class="hero-section">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container">
                <a class="navbar-brand fw-bold text-success" href="{{ route('frontend.home') }}">APNA SAFAR</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto gap-lg-2">
                        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Flights</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Our Services</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Best Deals</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">About Us</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Contact Us</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="hero-bg">
            <div class="container py-5">
                <div class="btn-group flight-tabs mb-4" role="group" aria-label="Flight tabs">
                    <button class="btn btn-success active">Flights</button>
                    <button class="btn btn-light">Group Tickets</button>
                    <button class="btn btn-light">Umrah Packages</button>
                </div>
                <x-forms.filter-form />
            </div>
        </div>
    </header>

    <section class="section-block bg-light-subtle">
        <div class="container text-center">
            <h2 class="section-title">Why Book With Us?</h2>
            <p class="section-subtitle">Discover the unmatched benefits and exclusive advantages of booking directly with us.</p>
            <div class="row g-3 mt-3">
                @foreach ($whyUsCards as $item)
                    <div class="col-sm-6 col-lg-3">
                        <article class="why-us-card shadow-sm h-100">
                            <div class="icon-circle mb-3"></div>
                            <h6>{{ $item['title'] }}</h6>
                            <p class="text-muted small mb-0">{{ $item['description'] }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-block">
        <div class="container">
            <h2 class="section-title text-center mb-4">Explore Group Tickets</h2>
            <div class="row g-3">
                @foreach ($groupCards as $item)
                    <div class="col-6 col-md-3">
                        <x-cards.group-card :title="$item['title']" :tag="$item['tag']" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-block bg-light-subtle">
        <div class="container">
            <h2 class="section-title text-center mb-4">Our Deals & Offers</h2>
            <div class="row g-3">
                @foreach ($packageCards as $item)
                    <div class="col-6 col-lg-3">
                        <x-cards.package-card :title="$item['title']" :subtitle="$item['subtitle']" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="footer-block">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 class="text-success fw-bold">APNA SAFAR</h5>
                    <p class="small mb-1">Your trusted travel partner for flights and packages.</p>
                    <p class="small mb-0">Lahore, Pakistan</p>
                </div>
                <div class="col-md-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-decoration-none text-dark">Home</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">About Us</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">Contact Us</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Customer Care</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-decoration-none text-dark">Agents</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">Promo Tickets</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">Group Tickets</a></li>
                        <li><a href="#" class="text-decoration-none text-dark">FAQ</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
@endsection
