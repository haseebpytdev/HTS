@php
    $codes = is_array($displayCurrencyOptions ?? null) ? $displayCurrencyOptions : [];
    $current = strtoupper((string) ($displayCurrency ?? ''));
@endphp

@if($codes !== [])
    <div class="content-shell p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small text-secondary mb-0">
            Show price estimates in your chosen currency. Estimates include a small conversion adjustment (see platform settings). Final charges are confirmed when you continue to book.
        </div>
        <form method="GET" action="{{ $hasSearched ? route('frontend.flights.results') : route('frontend.flights.search') }}" class="d-flex flex-wrap align-items-center gap-2">
            @if($hasSearched)
                @foreach(request()->except(['display_currency', 'page', 'append']) as $name => $value)
                    @if(is_array($value))
                        @foreach($value as $k => $v)
                            <input type="hidden" name="{{ $name }}[{{ $k }}]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
            @endif
            <label for="toolbar-display-currency" class="small text-secondary mb-0 text-nowrap">Show prices in</label>
            <select
                id="toolbar-display-currency"
                name="display_currency"
                class="form-select form-select-sm w-auto"
                aria-label="Display currency for price estimates"
                onchange="this.form.submit()"
            >
                @foreach($codes as $code)
                    <option value="{{ $code }}" @selected($current === strtoupper((string) $code))>{{ strtoupper((string) $code) }}</option>
                @endforeach
            </select>
        </form>
    </div>
@endif
