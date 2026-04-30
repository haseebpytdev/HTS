@php
    $offer = is_array($offer ?? null) ? $offer : [];
    $summary = is_array($offer['summary'] ?? null) ? $offer['summary'] : [];
    $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
    $fareComparison = is_array($fareComparison ?? null) ? $fareComparison : null;
@endphp

<div class="flight-itinerary-summary">
    @if($fareComparison !== null && ! empty($fareComparison['price_changed']))
        <div class="alert alert-warning border-0 d-flex align-items-start gap-2 mb-3" role="status">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <div class="fw-semibold">Price updated</div>
                <div class="small">
                    The airline returned a new total:
                    <span class="text-decoration-line-through">{{ $fareComparison['search_currency'] ?? '' }} {{ number_format((float) ($fareComparison['search_total'] ?? 0), 2) }}</span>
                    →
                    <span class="fw-semibold">{{ $fareComparison['confirmed_currency'] ?? '' }} {{ number_format((float) ($fareComparison['confirmed_total'] ?? 0), 2) }}</span>
                    (per traveler, supplier currency).
                </div>
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="d-flex align-items-start gap-3">
            @if(! empty($summary['carrier_logo_url']))
                <img
                    src="{{ $summary['carrier_logo_url'] }}"
                    alt=""
                    width="48"
                    height="48"
                    class="rounded border bg-white flex-shrink-0 flight-airline-logo"
                    loading="lazy"
                    decoding="async"
                >
            @else
                <div class="rounded border bg-light d-inline-flex align-items-center justify-content-center fw-bold text-brand-navy flex-shrink-0 flight-airline-logo-placeholder" style="width:48px;height:48px;">
                    {{ $summary['carrier_logo_text'] ?? 'FL' }}
                </div>
            @endif
            <div>
                <h2 class="h5 mb-1">{{ $summary['route_summary'] ?? 'Selected itinerary' }}</h2>
                <div class="small text-secondary">
                    {{ strtoupper((string) ($provider ?? '')) }} · {{ $summary['stop_label'] ?? '' }}
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if(! empty($summary['cabin_class']))
            <span class="badge rounded-pill text-bg-light border">{{ $summary['cabin_class'] }}</span>
        @endif
        @if(! empty($summary['baggage_summary']))
            <span class="badge rounded-pill text-bg-light border"><i class="bi bi-briefcase me-1"></i>{{ $summary['baggage_summary'] }}</span>
        @endif
        @if(! empty($summary['meal_note']))
            <span class="badge rounded-pill text-bg-light border"><i class="bi bi-cup-hot me-1"></i>{{ $summary['meal_note'] }}</span>
        @endif
        @if(! empty($summary['fare_brand']))
            <span class="badge rounded-pill text-bg-light border"><i class="bi bi-tags me-1"></i>{{ $summary['fare_brand'] }}</span>
        @endif
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="fw-bold fs-4">{{ $summary['departure_time'] ?? '--:--' }}</div>
            <div class="fw-semibold">{{ $summary['origin_airport'] ?? 'N/A' }}</div>
            <div class="small text-secondary">{{ $summary['origin_city'] ?? '' }}</div>
        </div>
        <div class="col-12 col-md-4 text-md-center align-self-center">
            <div class="small text-secondary">{{ $summary['duration_label'] ?? '' }}</div>
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <div class="fw-bold fs-4">{{ $summary['arrival_time'] ?? '--:--' }}</div>
            <div class="fw-semibold">{{ $summary['destination_airport'] ?? 'N/A' }}</div>
            <div class="small text-secondary">{{ $summary['destination_city'] ?? '' }}</div>
        </div>
    </div>

    @if($segments !== [])
        <div class="border rounded-3 p-3 bg-white mb-0">
            <div class="text-secondary text-uppercase fw-semibold small mb-2">Flights</div>
            <div class="d-grid gap-3">
                @foreach($segments as $segment)
                    @php
                        $seg = is_array($segment) ? $segment : [];
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start pb-3 @if(!$loop->last) border-bottom @endif flight-segment-row">
                        <div class="d-flex align-items-center gap-2">
                            @if(! empty($seg['airline_logo_url']))
                                <img src="{{ $seg['airline_logo_url'] }}" alt="" width="36" height="36" class="rounded border bg-white" loading="lazy">
                            @else
                                <div class="rounded border bg-light d-inline-flex align-items-center justify-content-center small fw-bold" style="width:36px;height:36px;">
                                    {{ strtoupper(substr((string) ($seg['marketing_carrier'] ?? 'FL'), 0, 2)) }}
                                </div>
                            @endif
                            <div>
                                <div class="fw-semibold">{{ $seg['carrier_display'] ?? ($seg['marketing_carrier'] ?? '') }}</div>
                                @if(! empty($seg['flight_number']))
                                    <div class="small text-secondary">Flight {{ $seg['flight_number'] }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex-grow-1 small">
                            <div class="fw-medium">
                                {{ $seg['departure_time'] ?? '' }} {{ $seg['departure_airport'] ?? '' }}
                                <span class="text-secondary">→</span>
                                {{ $seg['arrival_time'] ?? '' }} {{ $seg['arrival_airport'] ?? '' }}
                            </div>
                            <div class="text-secondary">{{ $seg['departure_date'] ?? '' }} @if(! empty($seg['duration_label'])) · {{ $seg['duration_label'] }} @endif</div>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                @if(! empty($seg['baggage_summary']))
                                    <span class="badge text-bg-light border"><i class="bi bi-briefcase me-1"></i>{{ $seg['baggage_summary'] }}</span>
                                @endif
                                @if(! empty($seg['meal_note']))
                                    <span class="badge text-bg-light border"><i class="bi bi-cup-hot me-1"></i>{{ $seg['meal_note'] }}</span>
                                @endif
                                @if(! empty($seg['cabin_class']))
                                    <span class="badge text-bg-light border">{{ $seg['cabin_class'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if(!$loop->last)
                        @php
                            $layover = $seg['layover_label'] ?? null;
                        @endphp
                        @if(! empty($layover))
                            <div class="small text-secondary ps-5">Layover {{ $layover }}</div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
