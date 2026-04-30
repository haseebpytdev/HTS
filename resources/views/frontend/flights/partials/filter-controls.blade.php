@php
    $filterState = is_array($filterState ?? null) ? $filterState : [];
    $filterOptions = is_array($filterOptions ?? null) ? $filterOptions : ['airlines' => [], 'cabins' => [], 'price_min' => null, 'price_max' => null];
    $perPage = (int) request()->query('per_page', 150);
@endphp

<form method="GET" action="{{ route('frontend.flights.results') }}" class="content-shell p-3 p-lg-4 mb-3">
    <input type="hidden" name="trip_type" value="{{ request()->query('trip_type') }}">
    <input type="hidden" name="from" value="{{ request()->query('from') }}">
    <input type="hidden" name="to" value="{{ request()->query('to') }}">
    <input type="hidden" name="origin" value="{{ request()->query('origin') }}">
    <input type="hidden" name="destination" value="{{ request()->query('destination') }}">
    <input type="hidden" name="departure_date" value="{{ request()->query('departure_date') }}">
    <input type="hidden" name="passengers" value="{{ request()->query('passengers') }}">
    <input type="hidden" name="cabin_class" value="{{ request()->query('cabin_class') }}">
    <input type="hidden" name="provider" value="{{ request()->query('provider') }}">
    @if(! empty($displayCurrency))
        <input type="hidden" name="display_currency" value="{{ $displayCurrency }}">
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="small text-secondary me-1 align-self-center">Quick sort:</span>
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'cheapest']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Cheapest</a>
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'fastest']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Fastest</a>
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'earliest_departure']) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Earliest</a>
    </div>

    <div class="row g-2 g-lg-3">
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-secondary mb-1">Sort</label>
            <select name="sort" class="form-select">
                <option value="best_value" @selected(($filterState['sort'] ?? 'best_value') === 'best_value')>Best value</option>
                <option value="cheapest" @selected(($filterState['sort'] ?? '') === 'cheapest')>Cheapest</option>
                <option value="fastest" @selected(($filterState['sort'] ?? '') === 'fastest')>Fastest</option>
                <option value="earliest_departure" @selected(($filterState['sort'] ?? '') === 'earliest_departure')>Earliest departure</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Stops</label>
            <select name="stops" class="form-select">
                <option value="any" @selected(($filterState['stops'] ?? 'any') === 'any')>Any</option>
                <option value="0" @selected(($filterState['stops'] ?? '') === '0')>Non-stop</option>
                <option value="1" @selected(($filterState['stops'] ?? '') === '1')>1 stop</option>
                <option value="2_plus" @selected(($filterState['stops'] ?? '') === '2_plus')>2+ stops</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Airline</label>
            <select name="airline" class="form-select">
                <option value="">All</option>
                @foreach(($filterOptions['airlines'] ?? []) as $airline)
                    <option value="{{ $airline }}" @selected(($filterState['airline'] ?? '') === $airline)>{{ $airline }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Departure</label>
            <select name="departure_window" class="form-select">
                <option value="">Any</option>
                <option value="morning" @selected(($filterState['departure_window'] ?? '') === 'morning')>Morning</option>
                <option value="afternoon" @selected(($filterState['departure_window'] ?? '') === 'afternoon')>Afternoon</option>
                <option value="evening" @selected(($filterState['departure_window'] ?? '') === 'evening')>Evening</option>
                <option value="night" @selected(($filterState['departure_window'] ?? '') === 'night')>Night</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Arrival</label>
            <select name="arrival_window" class="form-select">
                <option value="">Any</option>
                <option value="morning" @selected(($filterState['arrival_window'] ?? '') === 'morning')>Morning</option>
                <option value="afternoon" @selected(($filterState['arrival_window'] ?? '') === 'afternoon')>Afternoon</option>
                <option value="evening" @selected(($filterState['arrival_window'] ?? '') === 'evening')>Evening</option>
                <option value="night" @selected(($filterState['arrival_window'] ?? '') === 'night')>Night</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Cabin</label>
            <select name="filter_cabin_class" class="form-select">
                <option value="">All</option>
                @foreach(($filterOptions['cabins'] ?? []) as $cabin)
                    <option value="{{ $cabin }}" @selected(($filterState['filter_cabin_class'] ?? '') === $cabin)>{{ strtoupper(str_replace('_', ' ', $cabin)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Min price</label>
            <input type="number" step="0.01" min="0" class="form-control" name="price_min" value="{{ $filterState['price_min'] ?? '' }}" placeholder="{{ isset($filterOptions['price_min']) && $filterOptions['price_min'] !== null ? number_format((float) $filterOptions['price_min'], 2, '.', '') : '0.00' }}">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Max price</label>
            <input type="number" step="0.01" min="0" class="form-control" name="price_max" value="{{ $filterState['price_max'] ?? '' }}" placeholder="{{ isset($filterOptions['price_max']) && $filterOptions['price_max'] !== null ? number_format((float) $filterOptions['price_max'], 2, '.', '') : '0.00' }}">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small text-secondary mb-1">Results per page</label>
            <select name="per_page" class="form-select">
                <option value="100" @selected($perPage === 100)>100</option>
                <option value="150" @selected($perPage !== 100)>150</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-brand-green">Apply</button>
            <a href="{{ route('frontend.flights.results', array_filter(array_merge(
                request()->only(['trip_type', 'from', 'to', 'origin', 'destination', 'departure_date', 'passengers', 'provider', 'cabin_class']),
                ! empty($displayCurrency) ? ['display_currency' => $displayCurrency] : []
            ))) }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
