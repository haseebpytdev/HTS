@extends('layouts.app')

@section('content')
    <main class="container-xl py-3 py-md-4">
        <div class="row g-3">
            <aside class="col-12 col-lg-3">
                <div class="list-group shadow-sm rounded-3 agency-side-nav">
                    <a href="{{ route('agency.dashboard') }}" class="list-group-item list-group-item-action">Dashboard</a>
                    <a href="{{ route('agency.quotations.index') }}" class="list-group-item list-group-item-action">Quotations</a>
                    <a href="{{ route('agency.inquiries.index') }}" class="list-group-item list-group-item-action">Inquiries</a>
                    <a href="{{ route('agency.profile.edit') }}" class="list-group-item list-group-item-action">Profile / Settings</a>
                </div>
            </aside>
            <section class="col-12 col-lg-9">
                @yield('agency-content')
            </section>
        </div>
    </main>
@endsection
