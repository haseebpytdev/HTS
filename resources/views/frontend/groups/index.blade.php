@extends('layouts.frontend')

@section('title', 'Groups')

@section('content')
    <x-frontend.page-hero
        title="Group Tickets"
        subtitle="Browse open groups and fixed departures. Use filters to narrow by route or date, then inquire to hold seats or ask for a custom group quote."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Groups', 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            <div class="content-shell p-3 p-md-4 mb-4">
                <x-filters.group-filter-form
                    :filters="$filters"
                    :action="route('frontend.groups.index')"
                />
            </div>
            <div id="group-list">
                @include('frontend.groups.partials.list', ['groups' => $groups])
            </div>
            <div class="mt-3" id="group-pagination">
                @include('frontend.groups.partials.pagination', ['groups' => $groups])
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
