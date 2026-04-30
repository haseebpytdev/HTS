@php
    $summary = is_array($offer['summary'] ?? null) ? $offer['summary'] : [];
    $displayPrice = is_array($offer['display_price'] ?? null) ? $offer['display_price'] : null;
    $price = is_array($offer['price'] ?? null) ? $offer['price'] : null;
    $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
    $firstSegment = $segments[0] ?? [];
    $provider = strtoupper((string) ($driver ?? data_get($offer, 'provider', '')));
    $detailsId = (string) ($offer['details_id'] ?? ('offer-details-'.$loopIndex));
    $hasPrice = $displayPrice !== null || $price !== null;
    $showRoute = ! empty($summary['origin_airport']) || ! empty($summary['destination_airport']);
    $showBaggage = ! empty($summary['baggage_summary']);
    $showCabin = ! empty($summary['cabin_class']);
    $showFareBrand = ! empty($summary['fare_brand']);
    $showDuration = ! empty($summary['duration_label']);
    $showStop = ! empty($summary['stop_label']);
@endphp

<x-ui.flight-result-card class="flight-result-card-mobile">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
            @if(! empty($summary['carrier_logo_url']))
                <img
                    src="{{ $summary['carrier_logo_url'] }}"
                    alt=""
                    width="40"
                    height="40"
                    class="rounded-circle border bg-white flight-result-logo object-fit-cover"
                    loading="lazy"
                >
            @else
                <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center fw-bold text-brand-navy flight-result-logo">
                    {{ $summary['carrier_logo_text'] ?? 'FL' }}
                </div>
            @endif
            <div class="small">
                @if(! empty($summary['carrier']))
                    <span class="fw-semibold">{{ $summary['carrier'] }}</span>
                @endif
                @if(! empty($firstSegment['flight_number']))
                    <span class="text-secondary ms-1">{{ $firstSegment['flight_number'] }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($provider !== '')
                <x-ui.badge tone="info">{{ $provider }}</x-ui.badge>
            @endif
            <button
                class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $detailsId }}"
                aria-expanded="false"
                aria-controls="{{ $detailsId }}"
            >
                View Details
            </button>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-8">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                @if(! empty($summary['departure_time']))
                    <div class="flight-time-block">
                        <div class="flight-time-main">{{ $summary['departure_time'] }}</div>
                    </div>
                @endif
                @if($showDuration)
                    <span class="badge rounded-pill text-bg-light border px-3 py-2 fw-medium">{{ $summary['duration_label'] }}</span>
                @endif
                @if(! empty($summary['arrival_time']))
                    <div class="flight-time-block">
                        <div class="flight-time-main">{{ $summary['arrival_time'] }}</div>
                    </div>
                @endif
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 small mb-2">
                @if($showRoute)
                    <span>
                        {{ $summary['origin_city'] ?? $summary['origin_airport'] }}
                        @if(! empty($summary['origin_airport']) && ($summary['origin_city'] ?? null) !== ($summary['origin_airport'] ?? null))
                            ({{ $summary['origin_airport'] }})
                        @endif
                    </span>
                    <span class="text-secondary">-</span>
                    @if($showStop)
                        <span>{{ $summary['stop_label'] }}</span>
                        <span class="text-secondary">-</span>
                    @endif
                    <span>
                        {{ $summary['destination_city'] ?? $summary['destination_airport'] }}
                        @if(! empty($summary['destination_airport']) && ($summary['destination_city'] ?? null) !== ($summary['destination_airport'] ?? null))
                            ({{ $summary['destination_airport'] }})
                        @endif
                    </span>
                @elseif(! empty($summary['route_summary']))
                    <span>{{ $summary['route_summary'] }}</span>
                @endif
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 small">
                @if($showBaggage)
                    <span><i class="bi bi-briefcase me-1 text-secondary"></i>{{ $summary['baggage_summary'] }}</span>
                @endif
                @if($showCabin)
                    <span><i class="bi bi-person-workspace me-1 text-secondary"></i>{{ $summary['cabin_class'] }}</span>
                @endif
                @if($showFareBrand)
                    <span><i class="bi bi-tags me-1 text-secondary"></i>{{ $summary['fare_brand'] }}</span>
                @endif
                @if(! empty($summary['meal_note']))
                    <span><i class="bi bi-cup-hot me-1 text-secondary"></i>{{ $summary['meal_note'] }}</span>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="border rounded-4 p-3 bg-light h-100 d-flex flex-column justify-content-between flight-price-pane mobile-price-pane">
                <div>
                    @if($hasPrice)
                        <div class="text-secondary small text-uppercase fw-semibold mb-1">Price estimate</div>
                        @if($displayPrice !== null)
                            <div class="fw-bold fs-2 lh-sm mb-1" aria-label="Display currency total per traveler">
                                <span class="text-uppercase">{{ $displayPrice['display_currency'] ?? '' }}</span>
                                @if(! empty($displayPrice['display_amount_unavailable']))
                                    <span class="text-body-secondary">—</span>
                                @else
                                    <span class="text-nowrap">{{ $displayPrice['display_formatted'] ?? '' }}</span>
                                @endif
                            </div>
                            @if(! empty($displayPrice['display_amount_unavailable']))
                                <div class="small text-secondary mb-1">Could not load a display estimate right now. Please refresh the page in a moment.</div>
                            @endif
                        @elseif($price !== null && isset($price['currency'], $price['total_amount']))
                            <div class="small text-secondary">Fare estimate unavailable for this offer.</div>
                        @endif
                        <div class="small text-secondary">Per traveler</div>
                        @if($displayPrice !== null && ! empty($displayPrice['conversion_fee_percent']) && (float) $displayPrice['conversion_fee_percent'] > 0 && empty($displayPrice['display_amount_unavailable']))
                            <div class="small text-secondary mt-1">Includes {{ number_format((float) $displayPrice['conversion_fee_percent'], 2) }}% conversion adjustment on the displayed rate.</div>
                        @endif
                    @else
                        <div class="small text-secondary">Latest fare is confirmed when you continue.</div>
                    @endif
                </div>

                <div class="d-grid gap-2 mt-3">
                    <form method="POST" action="{{ route('frontend.flights.proceed') }}">
                        @csrf
                        <input type="hidden" name="correlation_id" value="{{ $correlationId }}">
                        <input type="hidden" name="offer_reference" value="{{ $offer['provider_offer_reference'] ?? $offer['id'] ?? '' }}">
                        <input type="hidden" name="provider" value="{{ $driver }}">
                        <button class="btn btn-brand-green w-100 rounded-3" type="submit">Continue</button>
                    </form>
                    <p class="as-helper-text mb-0">
                        We will confirm the latest price and availability with the airline before you enter traveler details.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @include('frontend.flights.partials.result-details', [
        'offer' => $offer,
        'detailsId' => $detailsId,
    ])
</x-ui.flight-result-card>
