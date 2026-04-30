@extends('layouts.frontend')

@section('title', 'Packages')

@section('content')
    <x-frontend.page-hero
        title="Umrah Packages"
        subtitle="Filter by destination, category, and budget. When you are ready, open a package to see departures and send a structured inquiry to our team."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Packages', 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            <div class="content-shell p-3 p-md-4 mb-4">
                <x-filters.package-filter-form
                    :filters="$filters"
                    :destinations="$destinations"
                    :categories="$categories"
                    :action="route('frontend.packages.index')"
                />
            </div>
            <div id="package-list">
                @include('frontend.packages.partials.list', ['packages' => $packages])
            </div>
            <div class="mt-3" id="package-pagination">
                @include('frontend.packages.partials.pagination', ['packages' => $packages])
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.js-ajax-filter-form');
            if (!form) return;

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const url = new URL(form.action);
                const params = new URLSearchParams(new FormData(form));
                params.set('ajax', '1');
                url.search = params.toString();

                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const payload = await response.json();
                document.querySelector(form.dataset.results).innerHTML = payload.html;
                document.querySelector(form.dataset.pagination).innerHTML = payload.pagination;
            });
        });
    </script>
@endsection
