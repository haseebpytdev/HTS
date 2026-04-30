@php
    $q = $quotation ?? null;
    $flightItem = $q?->items?->firstWhere('item_type', 'flight');
    $integrationMeta = is_array(data_get($flightItem?->meta, 'integration')) ? data_get($flightItem?->meta, 'integration') : [];
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Quotation Inputs</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Agency</label>
                        <select name="agency_id" class="form-select" required>
                            <option value="">Select agency</option>
                            @foreach($agencies as $agency)
                                <option value="{{ $agency->id }}" @selected(old('agency_id', $q?->agency_id) == $agency->id)>{{ $agency->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Inquiry (optional)</label>
                        <select name="inquiry_id" class="form-select">
                            <option value="">Select inquiry</option>
                            @foreach($inquiries as $inquiry)
                                <option value="{{ $inquiry->id }}" @selected(old('inquiry_id', $q?->inquiry_id) == $inquiry->id)>
                                    #{{ $inquiry->id }} - {{ $inquiry->name }} ({{ $inquiry->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4"><label class="form-label">Customer Name</label><input class="form-control" name="customer_name" value="{{ old('customer_name', $q?->customer_name) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Customer Email</label><input class="form-control" name="customer_email" value="{{ old('customer_email', $q?->customer_email) }}"></div>
                    <div class="col-md-4"><label class="form-label">Customer Phone</label><input class="form-control" name="customer_phone" value="{{ old('customer_phone', $q?->customer_phone) }}"></div>

                    <div class="col-md-3"><label class="form-label">Adults</label><input id="adults" class="form-control" type="number" min="1" name="adults" value="{{ old('adults', $q?->adults ?? 1) }}" required></div>
                    <div class="col-md-3"><label class="form-label">Children</label><input id="children" class="form-control" type="number" min="0" name="children" value="{{ old('children', $q?->children ?? 0) }}"></div>
                    <div class="col-md-3"><label class="form-label">Infants</label><input class="form-control" type="number" min="0" name="infants" value="{{ old('infants', $q?->infants ?? 0) }}"></div>
                    <div class="col-md-3"><label class="form-label">Currency</label><input class="form-control" name="currency" value="{{ old('currency', $q?->currency ?? 'PKR') }}" required></div>

                    <div class="col-md-6"><label class="form-label">Travel Date</label><input class="form-control" type="date" name="travel_date" value="{{ old('travel_date', optional($q?->travel_date)->format('Y-m-d')) }}"></div>
                    <div class="col-md-6"><label class="form-label">Return Date</label><input class="form-control" type="date" name="return_date" value="{{ old('return_date', optional($q?->return_date)->format('Y-m-d')) }}"></div>

                    <hr class="my-2">
                    <h3 class="h6 mb-0">Makkah Hotel</h3>
                    <div class="col-md-6">
                        <label class="form-label">Rate</label>
                        <select id="makkah_rate" name="makkah_hotel_rate_id" class="form-select" required>
                            @foreach($hotelRates as $rate)
                                <option value="{{ $rate->id }}" data-rate="{{ $rate->rate_per_night }}" @selected(old('makkah_hotel_rate_id') == $rate->id)>
                                    {{ $rate->roomType?->hotel?->name }} - {{ $rate->roomType?->name }} ({{ number_format($rate->rate_per_night,2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Nights</label><input id="makkah_nights" class="form-control" type="number" min="1" name="makkah_nights" value="{{ old('makkah_nights', 5) }}" required></div>
                    <div class="col-md-2"><label class="form-label">Basis</label><select id="makkah_basis" name="makkah_room_basis" class="form-select"><option>single</option><option>double</option><option>triple</option><option selected>quad</option></select></div>
                    <div class="col-md-2"><label class="form-label">Rooms</label><input id="makkah_rooms" class="form-control" type="number" min="1" name="makkah_rooms_count" value="{{ old('makkah_rooms_count') }}"></div>

                    <h3 class="h6 mb-0 mt-2">Madinah Hotel</h3>
                    <div class="col-md-6">
                        <label class="form-label">Rate</label>
                        <select id="madinah_rate" name="madinah_hotel_rate_id" class="form-select" required>
                            @foreach($hotelRates as $rate)
                                <option value="{{ $rate->id }}" data-rate="{{ $rate->rate_per_night }}" @selected(old('madinah_hotel_rate_id') == $rate->id)>
                                    {{ $rate->roomType?->hotel?->name }} - {{ $rate->roomType?->name }} ({{ number_format($rate->rate_per_night,2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Nights</label><input id="madinah_nights" class="form-control" type="number" min="1" name="madinah_nights" value="{{ old('madinah_nights', 4) }}" required></div>
                    <div class="col-md-2"><label class="form-label">Basis</label><select id="madinah_basis" name="madinah_room_basis" class="form-select"><option>single</option><option>double</option><option>triple</option><option selected>quad</option></select></div>
                    <div class="col-md-2"><label class="form-label">Rooms</label><input id="madinah_rooms" class="form-control" type="number" min="1" name="madinah_rooms_count" value="{{ old('madinah_rooms_count') }}"></div>

                    <hr class="my-2">
                    <h3 class="h6 mb-0">Other Components</h3>
                    <div class="col-md-4">
                        <label class="form-label">Visa Rate</label>
                        <select id="visa_rate" name="visa_rate_id" class="form-select">@foreach($visaRates as $rate)<option value="{{ $rate->id }}" data-amount="{{ $rate->amount }}">{{ $rate->visaType?->name }} ({{ number_format($rate->amount,2) }})</option>@endforeach</select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Mode</label><select id="visa_mode" name="visa_pricing_mode" class="form-select"><option value="per_person" selected>Per Person</option><option value="fixed">Fixed</option></select></div>
                    <div class="col-md-4">
                        <label class="form-label">Transport Rate</label>
                        <select id="transport_rate" name="transport_rate_id" class="form-select">@foreach($transportRates as $rate)<option value="{{ $rate->id }}" data-amount="{{ $rate->amount }}">{{ $rate->transportType?->name }} ({{ number_format($rate->amount,2) }})</option>@endforeach</select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Mode</label><select id="transport_mode" name="transport_pricing_mode" class="form-select"><option value="fixed" selected>Fixed</option><option value="per_person">Per Person</option></select></div>

                    <div class="col-md-4">
                        <label class="form-label">Flight Entry</label>
                        <select id="flight_rate" name="flight_entry_id" class="form-select">@foreach($flightEntries as $flight)<option value="{{ $flight->id }}" data-amount="{{ $flight->price }}">{{ $flight->origin }}-{{ $flight->destination }} {{ $flight->flight_no }} ({{ number_format($flight->price,2) }})</option>@endforeach</select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Mode</label><select id="flight_mode" name="flight_pricing_mode" class="form-select"><option value="per_person" selected>Per Person</option><option value="fixed">Fixed</option></select></div>
                    <div class="col-md-12">
                        <div class="border rounded p-3 bg-light-subtle">
                            <h3 class="h6 mb-2">Supplier Flight Selection (Optional)</h3>
                            <p class="small text-muted mb-2">Use normalized supplier references so admin quotation and booking can run Duffel pricing + booking through the integration layer.</p>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">Provider</label>
                                    <select class="form-select form-select-sm" name="integration_provider">
                                        <option value="">None</option>
                                        @foreach((array) config('integrations.supported_drivers', ['duffel']) as $driver)
                                            <option value="{{ $driver }}" @selected(old('integration_provider', data_get($integrationMeta, 'provider')) === $driver)>{{ strtoupper($driver) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Offer Reference</label>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        name="integration_offer_reference"
                                        value="{{ old('integration_offer_reference', data_get($integrationMeta, 'offer_reference')) }}"
                                        placeholder="off_..."
                                    >
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Correlation ID (optional)</label>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        name="integration_correlation_id"
                                        value="{{ old('integration_correlation_id', data_get($integrationMeta, 'correlation_id')) }}"
                                        placeholder="quotation-flight-correlation-id"
                                    >
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Selected Passenger IDs (optional, comma-separated)</label>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        name="integration_selected_passengers_csv"
                                        value="{{ old('integration_selected_passengers_csv', implode(',', (array) data_get($integrationMeta, 'selected_passengers', []))) }}"
                                        placeholder="pas_001,pas_002"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3"><label class="form-label">Extras Label</label><input class="form-control" name="extras_label" value="{{ old('extras_label', 'Misc') }}"></div>
                    <div class="col-md-2"><label class="form-label">Extras</label><input id="extras_amount" class="form-control" type="number" min="0" step="0.01" name="extras_amount" value="{{ old('extras_amount', 0) }}"></div>
                    <div class="col-md-1"><label class="form-label">Mode</label><select id="extras_mode" name="extras_mode" class="form-select"><option value="fixed" selected>F</option><option value="per_person">P</option></select></div>

                    <div class="col-md-3"><label class="form-label">Markup Type</label><select id="markup_type" name="markup_type" class="form-select"><option value="fixed">Fixed</option><option value="percentage">%</option></select></div>
                    <div class="col-md-3"><label class="form-label">Markup Value</label><input id="markup_value" class="form-control" type="number" min="0" step="0.01" name="markup_value" value="{{ old('markup_value', 0) }}"></div>
                    <div class="col-md-3"><label class="form-label">Discount</label><input id="discount_amount" class="form-control" type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', $q?->discount_amount ?? 0) }}"></div>
                    <div class="col-md-3"><label class="form-label">Promo Code</label><input class="form-control" name="promo_code" value="{{ old('promo_code', $q?->promo_code) }}" placeholder="Optional code"></div>
                    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['draft','sent','approved','rejected'] as $s)<option value="{{ $s }}" @selected(old('status', $q?->status ?? 'draft')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div class="col-md-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2">{{ old('notes', $q?->notes) }}</textarea></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <x-shared.quote-summary-panel />
        <div class="mt-3">
            <button class="btn btn-success w-100">{{ $submitLabel }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const getNum = (id) => parseFloat(document.getElementById(id)?.value || 0);
    const getRate = (id) => parseFloat(document.getElementById(id)?.selectedOptions?.[0]?.dataset?.rate || 0);
    const getAmount = (id) => parseFloat(document.getElementById(id)?.selectedOptions?.[0]?.dataset?.amount || 0);
    const getVal = (id) => document.getElementById(id)?.value || '';
    const capacity = (basis) => ({single:1,double:2,triple:3,quad:4}[basis] || 4);
    const fmt = (n) => Number(n || 0).toFixed(2);
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = fmt(value); };

    function rooms(basis, explicit, adults, children) {
        if (explicit > 0) return explicit;
        const units = adults + children;
        return Math.max(1, Math.ceil(units / capacity(basis)));
    }

    function component(amount, mode, pax) { return mode === 'per_person' ? amount * pax : amount; }

    function recalc() {
        const adults = getNum('adults');
        const children = getNum('children');
        const pax = Math.max(1, adults + children);

        const makkah = rooms(getVal('makkah_basis'), getNum('makkah_rooms'), adults, children) * getNum('makkah_nights') * getRate('makkah_rate');
        const madinah = rooms(getVal('madinah_basis'), getNum('madinah_rooms'), adults, children) * getNum('madinah_nights') * getRate('madinah_rate');
        const visa = component(getAmount('visa_rate'), getVal('visa_mode'), pax);
        const transport = component(getAmount('transport_rate'), getVal('transport_mode'), pax);
        const flight = component(getAmount('flight_rate'), getVal('flight_mode'), pax);
        const extras = component(getNum('extras_amount'), getVal('extras_mode'), pax);
        const subtotal = makkah + madinah + visa + transport + flight + extras;
        const markupType = getVal('markup_type');
        const markupValue = getNum('markup_value');
        const markup = markupType === 'percentage' ? (subtotal * markupValue / 100) : markupValue;
        const discount = getNum('discount_amount');
        const grand = Math.max(0, subtotal + markup - discount);
        const perPerson = grand / pax;

        set('sum-makkah', makkah);
        set('sum-madinah', madinah);
        set('sum-visa', visa);
        set('sum-transport', transport);
        set('sum-flight', flight);
        set('sum-extras', extras);
        set('sum-subtotal', subtotal);
        set('sum-markup', markup);
        set('sum-discount', discount);
        set('sum-grand', grand);
        set('sum-per-person', perPerson);
    }

    document.addEventListener('input', recalc);
    document.addEventListener('change', recalc);
    recalc();
})();
</script>
@endpush
