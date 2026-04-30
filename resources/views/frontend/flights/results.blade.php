@extends('layouts.frontend')

@section('title', 'Flight Search Results')

@section('content')
    <x-frontend.page-hero
        title="Search flights"
        subtitle="Run live supplier-backed search and review normalized offers before continuing."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Flights', 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            <div class="content-shell search-shell p-3 p-md-4 p-lg-5 mb-4">
                <x-forms.hero-search-panel />
            </div>

            @if($errors->any())
                <x-ui.alert tone="warning" title="Please update your search details:">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @if(session('flight_status'))
                <x-ui.alert tone="success">{{ session('flight_status') }}</x-ui.alert>
            @endif

            @if(session('flight_error'))
                <x-ui.alert tone="danger">{{ session('flight_error') }}</x-ui.alert>
            @endif

            @php
                $displayCurrencyOptions = $displayCurrencyOptions ?? [];
            @endphp
            @include('frontend.flights.partials.display-currency-toolbar', [
                'hasSearched' => $hasSearched,
                'displayCurrency' => $displayCurrency ?? '',
                'displayCurrencyOptions' => $displayCurrencyOptions,
            ])

            @if($hasSearched && $errorMessage)
                <x-ui.alert tone="danger" title="Flight search is unavailable">
                    {{ $errorMessage }}
                </x-ui.alert>
            @endif

            @if($hasSearched && ! $errorMessage)
                @php
                    $resultsSummary = is_array($resultsSummary ?? null) ? $resultsSummary : ['total_offers' => count($offers), 'visible_offers' => count($offers), 'route' => null, 'providers' => []];
                    $filterState = is_array($filterState ?? null) ? $filterState : [];
                    $filterOptions = is_array($filterOptions ?? null) ? $filterOptions : ['airlines' => [], 'cabins' => [], 'price_min' => null, 'price_max' => null];
                    $providersUsed = is_array($resultsSummary['providers'] ?? null) ? $resultsSummary['providers'] : [];
                    $offersPagination = $offersPagination ?? null;
                @endphp
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="as-page-title mb-1">Flight results</h2>
                        <p class="as-body-text mb-0" id="results-summary-line">
                            Showing
                            {{ $resultsSummary['page_from'] ?? ($resultsSummary['visible_offers'] > 0 ? 1 : 0) }}
                            -
                            {{ $resultsSummary['page_to'] ?? ($resultsSummary['visible_offers'] ?? count($offers)) }}
                            of
                            {{ $resultsSummary['visible_offers'] ?? count($offers) }} filtered offer(s)
                            ({{ $resultsSummary['total_offers'] ?? count($offers) }} total)
                            @if(! empty($resultsSummary['route']))
                                for {{ $resultsSummary['route'] }}
                            @endif
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <x-ui.badge tone="muted">Sabre attempted</x-ui.badge>
                            <x-ui.badge tone="primary">{{ $driver ? strtoupper($driver) : 'Provider pending' }} final provider</x-ui.badge>
                        </div>
                    </div>
                    <div class="as-helper-text text-start text-md-end results-meta-panel">
                        @if(! empty($displayCurrency))
                            <div>Display currency: <span class="fw-semibold text-uppercase">{{ $displayCurrency }}</span></div>
                        @endif
                        @if($driver)
                            <div>Provider: <span class="fw-semibold text-uppercase">{{ $driver }}</span></div>
                        @endif
                        @if(! empty($integrationMode))
                            <div>
                                Mode:
                                <span class="badge {{ $integrationMode === 'sandbox' ? 'bg-warning text-dark' : 'bg-success' }}">
                                    {{ $integrationMode === 'sandbox' ? 'Sandbox / Test' : 'Production' }}
                                </span>
                            </div>
                        @endif
                        @if($correlationId)
                            <div>Correlation ID: <code>{{ $correlationId }}</code></div>
                        @endif
                        @if($providersUsed !== [])
                            <div>Providers used: <span class="fw-semibold text-uppercase">{{ implode(', ', $providersUsed) }}</span></div>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-nowrap flex-md-wrap gap-2 mb-3 results-sort-chips overflow-auto">
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'cheapest']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Cheapest</a>
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'fastest']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Fastest</a>
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'earliest_departure']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Earliest</a>
                </div>

                <div class="d-none d-lg-block position-sticky mb-3" style="top: 90px; z-index: 100;">
                    @include('frontend.flights.partials.filter-controls', [
                        'filterState' => $filterState,
                        'filterOptions' => $filterOptions,
                        'displayCurrency' => $displayCurrency ?? '',
                    ])
                </div>

                <div class="d-lg-none mb-3">
                    <button
                        type="button"
                        class="btn btn-outline-brand-navy rounded-pill w-100"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#mobileResultsFilterDrawer"
                        aria-controls="mobileResultsFilterDrawer"
                    >
                        Filter & sort results
                    </button>
                </div>
                <x-ui.filter-drawer id="mobileResultsFilterDrawer" title="Filter & sort results">
                    @include('frontend.flights.partials.filter-controls', [
                        'filterState' => $filterState,
                        'filterOptions' => $filterOptions,
                        'displayCurrency' => $displayCurrency ?? '',
                    ])
                </x-ui.filter-drawer>

                @if(count($offers) === 0)
                    <x-ui.empty-state
                        title="No flights found for this search"
                        message="Modify your search, try another date, or request a manual quote from our team."
                        icon="bi-airplane"
                    >
                        <a href="{{ route('frontend.flights.search') }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Modify search</a>
                        <a href="{{ request()->fullUrlWithQuery(['departure_date' => now()->addDay()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Try another date</a>
                        <a href="{{ route('frontend.inquiries.quote') }}" class="btn btn-sm btn-brand-green text-white rounded-pill">Request manual quote</a>
                    </x-ui.empty-state>
                @else
                    <div class="row g-3 g-lg-4" id="results-offers-list">
                        @include('frontend.flights.partials.offers-list', [
                            'offers' => $offers,
                            'driver' => $driver,
                            'correlationId' => $correlationId,
                        ])
                    </div>
                    @if($offersPagination instanceof \Illuminate\Pagination\LengthAwarePaginator && $offersPagination->lastPage() > 1)
                        <div class="mt-4">
                            <div class="d-flex justify-content-center mb-2">
                                {{ $offersPagination->onEachSide(1)->links() }}
                            </div>
                            @if($offersPagination->hasMorePages())
                                <div class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        id="load-more-offers-btn"
                                        data-next-url="{{ $offersPagination->appends(request()->query())->nextPageUrl() }}"
                                    >
                                        Load more results
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            @endif

            @if(! $hasSearched)
                <x-ui.alert tone="info" class="mb-0">
                    Enter a route and date to see real supplier-backed flight offers.
                </x-ui.alert>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const loadMoreButton = document.getElementById('load-more-offers-btn');
            const offersList = document.getElementById('results-offers-list');
            const summaryLine = document.getElementById('results-summary-line');
            if (!loadMoreButton || !offersList) {
                return;
            }

            const setLoadingState = (loading) => {
                loadMoreButton.disabled = loading;
                loadMoreButton.textContent = loading ? 'Loading...' : 'Load more results';
            };

            const updateSummary = (summary) => {
                if (!summaryLine || !summary) {
                    return;
                }
                const pageFrom = summary.page_from ?? 0;
                const pageTo = summary.page_to ?? 0;
                const visible = summary.visible_offers ?? 0;
                const total = summary.total_offers ?? 0;
                const routeSuffix = summary.route ? ` for ${summary.route}` : '';
                summaryLine.textContent = `Showing ${pageFrom} - ${pageTo} of ${visible} filtered offer(s) (${total} total)${routeSuffix}`;
            };

            loadMoreButton.addEventListener('click', async () => {
                const nextUrl = loadMoreButton.getAttribute('data-next-url');
                if (!nextUrl) {
                    return;
                }

                try {
                    setLoadingState(true);
                    const url = new URL(nextUrl, window.location.origin);
                    url.searchParams.set('append', '1');

                    const response = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load more offers');
                    }

                    const payload = await response.json();
                    const data = payload?.data ?? {};
                    if (typeof data.html === 'string' && data.html.trim() !== '') {
                        offersList.insertAdjacentHTML('beforeend', data.html);
                    }

                    updateSummary(data.summary ?? null);

                    if (data.has_more && data.next_page_url) {
                        loadMoreButton.setAttribute('data-next-url', data.next_page_url);
                        setLoadingState(false);
                        return;
                    }

                    loadMoreButton.remove();
                } catch (_error) {
                    setLoadingState(false);
                }
            });
        })();
    </script>
@endpush
