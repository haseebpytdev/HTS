<x-ui.search-form-card class="hero-search-panel" title="Search premium flight offers" subtitle="Compare live fares across Sabre and Duffel-enabled suppliers, then continue with instant revalidation.">
    <form method="GET" action="{{ route('frontend.flights.results') }}">
    @php
        $persistDisplayCurrency = strtoupper(trim((string) (request('display_currency') ?: (session('display_currency') ?? ''))));
    @endphp
    @if(strlen($persistDisplayCurrency) === 3)
        <input type="hidden" name="display_currency" value="{{ $persistDisplayCurrency }}">
    @endif
    <div class="trip-tabs d-flex flex-wrap gap-2 align-items-center mb-4">
        <div class="form-check m-0 trip-tab-item">
            <input class="form-check-input" type="radio" name="trip_type" id="tripOne" value="one_way" @checked(request('trip_type', 'one_way') === 'one_way')>
            <label class="form-check-label fw-medium" for="tripOne">One Way</label>
        </div>
        <div class="form-check m-0 trip-tab-item">
            <input class="form-check-input" type="radio" name="trip_type" id="tripRound" value="roundtrip" @checked(request('trip_type') === 'roundtrip')>
            <label class="form-check-label fw-medium" for="tripRound">Roundtrip</label>
        </div>
        <div class="form-check m-0 trip-tab-item">
            <input class="form-check-input" type="radio" name="trip_type" id="tripMulti" value="multi_city" @checked(request('trip_type') === 'multi_city')>
            <label class="form-check-label fw-medium" for="tripMulti">Multi City</label>
        </div>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-4 col-lg-3 position-relative">
            <label class="form-label small text-secondary mb-1" for="flight-from-input">From</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-geo-alt text-brand-green"></i></span>
                <input
                    id="flight-from-input"
                    type="text"
                    class="form-control as-input border-start-0 ps-0 js-airport-combobox"
                    name="from"
                    placeholder="City, country, or airport (e.g. Lahore / Pakistan / LHE)"
                    aria-label="From"
                    autocomplete="off"
                    value="{{ request('from') }}"
                >
                <input type="hidden" id="flight-from-code" name="origin" value="{{ request('origin') }}">
            </div>
            <ul
                id="flight-from-dropdown"
                class="list-group position-absolute w-100 mt-1 shadow-sm border rounded d-none airport-dropdown-panel"
                style="z-index: 1050; max-height: 280px; overflow-y: auto;"
                role="listbox"
                aria-label="From airport suggestions"
            ></ul>
        </div>
        <div class="col-auto align-self-end pb-2 d-none d-md-block">
            <button type="button" class="btn btn-icon-swap rounded-circle border-0" title="Swap" aria-label="Swap from and to">
                <i class="bi bi-arrow-left-right"></i>
            </button>
        </div>
        <div class="col-md-4 col-lg-3 position-relative">
            <label class="form-label small text-secondary mb-1" for="flight-to-input">To</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-geo-alt-fill text-brand-navy"></i></span>
                <input
                    id="flight-to-input"
                    type="text"
                    class="form-control as-input border-start-0 ps-0 js-airport-combobox"
                    name="to"
                    placeholder="City, country, or airport (e.g. Dubai / UAE / DXB)"
                    aria-label="To"
                    autocomplete="off"
                    value="{{ request('to') }}"
                >
                <input type="hidden" id="flight-to-code" name="destination" value="{{ request('destination') }}">
            </div>
            <ul
                id="flight-to-dropdown"
                class="list-group position-absolute w-100 mt-1 shadow-sm border rounded d-none airport-dropdown-panel"
                style="z-index: 1050; max-height: 280px; overflow-y: auto;"
                role="listbox"
                aria-label="To airport suggestions"
            ></ul>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label small text-secondary mb-1">Departure</label>
            <input type="date" name="departure_date" class="form-control as-input" aria-label="Departure date" value="{{ request('departure_date') }}">
        </div>
        <div class="col-6 col-md-4 col-lg-2 js-return-date-field {{ request('trip_type') === 'roundtrip' ? '' : 'd-none' }}">
            <label class="form-label small text-secondary mb-1">Return</label>
            <input type="date" name="return_date" class="form-control as-input" aria-label="Return date" value="{{ request('return_date') }}">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label small text-secondary mb-1">Passengers</label>
            <select class="form-select as-select" name="passengers" aria-label="Passengers">
                <option value="1" @selected(request('passengers') === '1')>1 Adult</option>
                <option value="2" @selected(request('passengers', '2') === '2')>2 Adults</option>
                <option value="3" @selected(request('passengers') === '3')>2 Adults, 1 Child</option>
                <option value="4" @selected(request('passengers') === '4')>2 Adults, 1 Child, 1 Infant</option>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label small text-secondary mb-1">Class</label>
            <select class="form-select as-select" name="cabin_class" aria-label="Cabin class">
                <option value="economy" @selected(request('cabin_class', 'economy') === 'economy')>Economy</option>
                <option value="premium_economy" @selected(request('cabin_class') === 'premium_economy')>Premium Economy</option>
                <option value="business" @selected(request('cabin_class') === 'business')>Business</option>
            </select>
        </div>
        <div class="col-12 col-lg-auto ms-lg-auto d-grid">
            <label class="form-label small text-secondary mb-1 d-none d-lg-block">&nbsp;</label>
            <button type="submit" class="btn btn-brand-green btn-lg rounded-pill px-5 text-white d-inline-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-search"></i> Search
            </button>
        </div>
    </div>
    <p class="as-helper-text mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Search now shows live normalized supplier results. For custom package planning, you can still use the dedicated quote inquiry page.
    </p>
    </form>
</x-ui.search-form-card>

@once
    @push('scripts')
        <script src="{{ asset('js/airport-dataset-loader.js') }}"></script>
        <script src="{{ asset('js/airport-autocomplete.js') }}"></script>
        <script>
            (() => {
                const tripTypeInputs = document.querySelectorAll('input[name="trip_type"]');
                const returnDateField = document.querySelector('.js-return-date-field');
                if (!tripTypeInputs.length || !returnDateField) {
                    return;
                }

                const syncReturnDateVisibility = () => {
                    const selected = document.querySelector('input[name="trip_type"]:checked');
                    const showReturn = selected && selected.value === 'roundtrip';
                    returnDateField.classList.toggle('d-none', !showReturn);
                };

                tripTypeInputs.forEach((input) => {
                    input.addEventListener('change', syncReturnDateVisibility);
                });
                syncReturnDateVisibility();
            })();
        </script>
    @endpush
@endonce
