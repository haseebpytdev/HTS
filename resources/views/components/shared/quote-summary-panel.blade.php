@props([
    'totals' => [
        'makkah_hotel' => 0,
        'madinah_hotel' => 0,
        'visa' => 0,
        'transport' => 0,
        'flight' => 0,
        'extras' => 0,
        'subtotal' => 0,
        'markup' => 0,
        'discount' => 0,
        'grand_total' => 0,
        'per_person' => 0,
    ]
])

<aside class="quote-summary-panel shadow-sm bg-white p-3 rounded">
    <h6 class="mb-3">Quote Summary</h6>
    <dl class="row mb-0 small">
        <dt class="col-7">Makkah Hotel</dt><dd class="col-5 text-end" id="sum-makkah">{{ number_format((float) $totals['makkah_hotel'], 2) }}</dd>
        <dt class="col-7">Madinah Hotel</dt><dd class="col-5 text-end" id="sum-madinah">{{ number_format((float) $totals['madinah_hotel'], 2) }}</dd>
        <dt class="col-7">Visa</dt><dd class="col-5 text-end" id="sum-visa">{{ number_format((float) $totals['visa'], 2) }}</dd>
        <dt class="col-7">Transport</dt><dd class="col-5 text-end" id="sum-transport">{{ number_format((float) $totals['transport'], 2) }}</dd>
        <dt class="col-7">Flight</dt><dd class="col-5 text-end" id="sum-flight">{{ number_format((float) $totals['flight'], 2) }}</dd>
        <dt class="col-7">Extras</dt><dd class="col-5 text-end" id="sum-extras">{{ number_format((float) $totals['extras'], 2) }}</dd>
        <dt class="col-7 fw-semibold">Subtotal</dt><dd class="col-5 text-end fw-semibold" id="sum-subtotal">{{ number_format((float) $totals['subtotal'], 2) }}</dd>
        <dt class="col-7">Markup</dt><dd class="col-5 text-end" id="sum-markup">{{ number_format((float) $totals['markup'], 2) }}</dd>
        <dt class="col-7">Discount</dt><dd class="col-5 text-end" id="sum-discount">{{ number_format((float) $totals['discount'], 2) }}</dd>
        <dt class="col-7 fw-bold">Grand Total</dt><dd class="col-5 text-end fw-bold" id="sum-grand">{{ number_format((float) $totals['grand_total'], 2) }}</dd>
        <dt class="col-7">Per Person</dt><dd class="col-5 text-end" id="sum-per-person">{{ number_format((float) $totals['per_person'], 2) }}</dd>
    </dl>
</aside>
