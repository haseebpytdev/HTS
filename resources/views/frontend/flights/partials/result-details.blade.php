@php
    $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
    $fareOptions = is_array($offer['fare_options'] ?? null) ? $offer['fare_options'] : [];
@endphp

<div class="collapse mt-3" id="{{ $detailsId }}">
    <div class="border-top pt-3">
        @if($fareOptions !== [])
            <div class="mb-4 fare-options-block">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <h4 class="h6 mb-0">Select a fare option</h4>
                    <button
                        type="button"
                        class="btn btn-link btn-sm text-decoration-none p-0"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $detailsId }}"
                        aria-expanded="true"
                        aria-controls="{{ $detailsId }}"
                    >
                        <i class="bi bi-chevron-up me-1"></i>Hide
                    </button>
                </div>
                <div class="row g-3 fare-options-row">
                    @foreach($fareOptions as $fareIndex => $fareOption)
                        @php
                            $title = trim((string) ($fareOption['title'] ?? $fareOption['name'] ?? ''));
                            $badges = is_array($fareOption['badges'] ?? null) ? $fareOption['badges'] : [];
                            $features = is_array($fareOption['features'] ?? null) ? $fareOption['features'] : [];
                            $farePrice = is_array($fareOption['price'] ?? null) ? $fareOption['price'] : null;
                        @endphp
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100 d-flex flex-column justify-content-between fare-option-card">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        @if($title !== '')
                                            <h5 class="h4 mb-0 fs-4">{{ $title }}</h5>
                                        @endif
                                        @foreach($badges as $badge)
                                            @if(is_string($badge) && trim($badge) !== '')
                                                <span class="badge fare-option-badge">{{ trim($badge) }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if($features !== [])
                                        <ul class="list-unstyled small mb-0 d-grid gap-1 fare-option-features">
                                            @foreach($features as $feature)
                                                @if(is_array($feature) && ! empty($feature['label']) && ! empty($feature['value']))
                                                    <li class="d-flex justify-content-between gap-2">
                                                        <span class="text-secondary"><i class="bi bi-dot"></i> {{ $feature['label'] }}</span>
                                                        <span>{{ $feature['value'] }}</span>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                @if($farePrice !== null)
                                    <div class="mt-3">
                                        <div class="btn btn-primary w-100 disabled fare-option-price">
                                            {{ strtoupper((string) ($farePrice['currency'] ?? '')) }} {{ $farePrice['formatted'] ?? '' }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <h4 class="h6 mb-3">Flight details</h4>
                <div class="d-grid gap-3">
                    @foreach($segments as $segmentIndex => $segment)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                <div class="fw-semibold">
                                    {{ $segment['carrier_display'] ?? '' }}
                                    @if(! empty($segment['flight_number']))
                                        {{ $segment['flight_number'] }}
                                    @endif
                                </div>
                                @if(! empty($segment['duration_label']))
                                    <div class="small text-secondary">
                                        Duration: {{ $segment['duration_label'] }}
                                    </div>
                                @endif
                            </div>

                            <div class="row g-3 small">
                                <div class="col-12 col-md-6">
                                    <div class="text-secondary text-uppercase fw-semibold mb-1">Departure</div>
                                    <div class="fw-semibold">
                                        {{ $segment['departure_airport'] ?? '' }}
                                        @if(! empty($segment['departure_city']))
                                            · {{ $segment['departure_city'] }}
                                        @endif
                                    </div>
                                    @if(! empty($segment['departure_airport_name']))
                                        <div>{{ $segment['departure_airport_name'] }}</div>
                                    @endif
                                    @if(! empty($segment['departure_date']) || ! empty($segment['departure_time']))
                                        <div>{{ trim((string) (($segment['departure_date'] ?? '').' '.($segment['departure_time'] ?? ''))) }}</div>
                                    @endif
                                    @if(! empty($segment['departure_terminal']))
                                        <div>Terminal: {{ $segment['departure_terminal'] }}</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="text-secondary text-uppercase fw-semibold mb-1">Arrival</div>
                                    <div class="fw-semibold">
                                        {{ $segment['arrival_airport'] ?? '' }}
                                        @if(! empty($segment['arrival_city']))
                                            · {{ $segment['arrival_city'] }}
                                        @endif
                                    </div>
                                    @if(! empty($segment['arrival_airport_name']))
                                        <div>{{ $segment['arrival_airport_name'] }}</div>
                                    @endif
                                    @if(! empty($segment['arrival_date']) || ! empty($segment['arrival_time']))
                                        <div>{{ trim((string) (($segment['arrival_date'] ?? '').' '.($segment['arrival_time'] ?? ''))) }}</div>
                                    @endif
                                    @if(! empty($segment['arrival_terminal']))
                                        <div>Terminal: {{ $segment['arrival_terminal'] }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-2 small mt-1">
                                <div class="col-12 col-md-6">
                                    @if(! empty($segment['marketing_carrier']))
                                        <div><span class="text-secondary">Marketing carrier:</span> {{ $segment['marketing_carrier'] }}</div>
                                    @endif
                                    @if(! empty($segment['operating_carrier']))
                                        <div><span class="text-secondary">Operating carrier:</span> {{ $segment['operating_carrier'] }}</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6">
                                    @if(! empty($segment['cabin_class']))
                                        <div><span class="text-secondary">Cabin:</span> {{ $segment['cabin_class'] }}</div>
                                    @endif
                                    @if(! empty($segment['baggage_summary']))
                                        <div><span class="text-secondary">Baggage:</span> {{ $segment['baggage_summary'] }}</div>
                                    @endif
                                </div>
                            </div>

                            @if(($segmentIndex < count($segments) - 1) && ! empty($segment['layover_label']))
                                <div class="alert alert-light border small mt-3 mb-0">
                                    Layover: {{ $segment['layover_label'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @php
                $noteRows = [
                    ['label' => 'Baggage rules', 'value' => data_get($segments, '0.baggage_rules')],
                    ['label' => 'Change notes', 'value' => data_get($segments, '0.change_notes')],
                    ['label' => 'Refund notes', 'value' => data_get($segments, '0.refund_notes')],
                    ['label' => 'Fare conditions', 'value' => data_get($segments, '0.fare_conditions')],
                    ['label' => 'Booking conditions', 'value' => data_get($segments, '0.booking_conditions')],
                ];
                $visibleNotes = array_values(array_filter($noteRows, static fn (array $row): bool => is_string($row['value']) && trim($row['value']) !== ''));
            @endphp
            @if($visibleNotes !== [])
                <div class="col-12 col-lg-4">
                    <h4 class="h6 mb-3">Fare notes</h4>
                    <div class="border rounded-3 p-3 small">
                        @foreach($visibleNotes as $row)
                            <div class="{{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                <div class="text-secondary text-uppercase fw-semibold">{{ $row['label'] }}</div>
                                <div>{{ $row['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="d-none" data-offer-reference="{{ $offer['provider_offer_reference'] ?? '' }}" data-offer-id="{{ $offer['id'] ?? '' }}"></div>
    </div>
</div>
