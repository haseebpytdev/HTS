<?php

namespace App\ViewModels\Frontend;

use App\Services\Currency\FareDisplayConversionService;
use App\Services\Travel\AirportDirectoryService;
use Carbon\CarbonImmutable;

/**
 * Presents normalized flight offers for the public UI.
 *
 * Production rules: fare conversion runs server-side only; original supplier
 * `price` is always preserved on the offer array; only normalized fields are
 * exposed to Blade (no raw supplier payloads).
 */
class FlightSearchResultViewModel
{
    public function __construct(
        private readonly FareDisplayConversionService $fareConversion,
        private readonly AirportDirectoryService $airportDirectory,
    ) {
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public function present(array $offer, string $displayCurrency): array
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $offer['segments'] = $this->presentSegments($segments);
        $offer['summary'] = $this->buildSummary($offer['segments'], $offer);
        $offer['fare_options'] = $this->presentFareOptions($offer['fare_options'] ?? null);
        $offer['details_id'] = 'offer-details-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($offer['id'] ?? uniqid('offer-', false)));

        $price = is_array($offer['price'] ?? null) ? $offer['price'] : null;
        if ($price === null) {
            return $offer;
        }

        $supplierCurrency = strtoupper(trim((string) ($price['currency'] ?? '')));
        $totalAmount = $price['total_amount'] ?? null;

        if ($supplierCurrency === '' || ! is_numeric($totalAmount)) {
            return $offer;
        }

        $offer['display_price'] = $this->fareConversion->convert(
            amount: (float) $totalAmount,
            supplierCurrency: $supplierCurrency,
            displayCurrency: $displayCurrency,
        );

        return $offer;
    }

    /**
     * @return list<array{title:string,badges:list<string>,features:list<array{label:string,value:string}>,price:?array{currency:string,formatted:string}}>
     */
    private function presentFareOptions(mixed $fareOptions): array
    {
        if (! is_array($fareOptions)) {
            return [];
        }

        $rows = [];
        foreach ($fareOptions as $option) {
            if (! is_array($option)) {
                continue;
            }

            $title = trim((string) ($option['title'] ?? $option['name'] ?? ''));
            $badges = [];
            foreach (($option['badges'] ?? []) as $badge) {
                if (is_string($badge) && trim($badge) !== '') {
                    $badges[] = trim($badge);
                }
            }

            $features = [];
            foreach (($option['features'] ?? []) as $feature) {
                if (! is_array($feature)) {
                    continue;
                }
                $label = trim((string) ($feature['label'] ?? ''));
                $value = trim((string) ($feature['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $features[] = ['label' => $label, 'value' => $value];
            }

            $price = null;
            if (is_array($option['price'] ?? null)) {
                $currency = strtoupper(trim((string) ($option['price']['currency'] ?? '')));
                $formatted = trim((string) ($option['price']['formatted'] ?? ''));
                if ($currency !== '' && $formatted !== '') {
                    $price = ['currency' => $currency, 'formatted' => $formatted];
                }
            }

            if ($title === '' && $features === [] && $price === null) {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'badges' => $badges,
                'features' => $features,
                'price' => $price,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function presentSegments(array $segments): array
    {
        $presented = [];
        $count = count($segments);

        foreach ($segments as $index => $segment) {
            $departureAirport = strtoupper((string) ($segment['departure_airport'] ?? ''));
            $arrivalAirport = strtoupper((string) ($segment['arrival_airport'] ?? ''));
            $departureMeta = $this->airportMeta($departureAirport);
            $arrivalMeta = $this->airportMeta($arrivalAirport);
            $departureAt = $this->parseDateTime($segment['departure_at'] ?? null);
            $arrivalAt = $this->parseDateTime($segment['arrival_at'] ?? null);
            $nextSegment = $index < ($count - 1) ? ($segments[$index + 1] ?? null) : null;
            $nextDeparture = is_array($nextSegment) ? $this->parseDateTime($nextSegment['departure_at'] ?? null) : null;

            $marketingCarrier = $segment['marketing_carrier'] ?? null;
            $presented[] = [
                'departure_airport' => $departureAirport,
                'arrival_airport' => $arrivalAirport,
                'departure_city' => $departureMeta['city'],
                'arrival_city' => $arrivalMeta['city'],
                'departure_airport_name' => $departureMeta['airport'],
                'arrival_airport_name' => $arrivalMeta['airport'],
                'departure_label' => $departureMeta['label'],
                'arrival_label' => $arrivalMeta['label'],
                'departure_at' => $segment['departure_at'] ?? null,
                'arrival_at' => $segment['arrival_at'] ?? null,
                'departure_time' => $departureAt?->format('H:i'),
                'arrival_time' => $arrivalAt?->format('H:i'),
                'departure_date' => $departureAt?->format('D, d M Y'),
                'arrival_date' => $arrivalAt?->format('D, d M Y'),
                'duration_minutes' => $this->durationMinutes($departureAt, $arrivalAt),
                'duration_label' => $this->durationLabel($this->durationMinutes($departureAt, $arrivalAt)),
                'marketing_carrier' => $marketingCarrier,
                'marketing_carrier_name' => $segment['marketing_carrier_name'] ?? null,
                'operating_carrier' => $segment['operating_carrier'] ?? null,
                'carrier_display' => $this->carrierDisplayLabel($segment),
                'airline_logo_url' => $this->airlineLogoUrl(is_string($marketingCarrier) ? $marketingCarrier : null),
                'flight_number' => $segment['flight_number'] ?? null,
                'cabin_class' => $segment['cabin_class'] ?? null,
                'baggage_summary' => $segment['baggage_summary'] ?? null,
                'meal_note' => $segment['meal_note'] ?? null,
                'baggage_rules' => $segment['baggage_rules'] ?? null,
                'fare_brand' => $segment['fare_brand'] ?? null,
                'change_notes' => $segment['change_notes'] ?? null,
                'refund_notes' => $segment['refund_notes'] ?? null,
                'fare_conditions' => $segment['fare_conditions'] ?? null,
                'booking_conditions' => $segment['booking_conditions'] ?? null,
                'departure_terminal' => $segment['departure_terminal'] ?? null,
                'arrival_terminal' => $segment['arrival_terminal'] ?? null,
                'layover_minutes' => $this->durationMinutes($arrivalAt, $nextDeparture),
                'layover_label' => $this->durationLabel($this->durationMinutes($arrivalAt, $nextDeparture)),
            ];
        }

        return $presented;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function buildSummary(array $segments, array $offer): array
    {
        $firstSegment = $segments[0] ?? null;
        $lastSegment = $segments !== [] ? $segments[count($segments) - 1] : null;
        $stopCount = max(0, count($segments) - 1);
        $journeyMinutes = is_array($firstSegment) && is_array($lastSegment)
            ? $this->durationMinutes(
                $this->parseDateTime($firstSegment['departure_at'] ?? null),
                $this->parseDateTime($lastSegment['arrival_at'] ?? null),
            )
            : null;

        $originCity = is_array($firstSegment) ? ($firstSegment['departure_city'] ?? null) : null;
        $destinationCity = is_array($lastSegment) ? ($lastSegment['arrival_city'] ?? null) : null;
        $originAirport = is_array($firstSegment) ? ($firstSegment['departure_airport'] ?? null) : null;
        $destinationAirport = is_array($lastSegment) ? ($lastSegment['arrival_airport'] ?? null) : null;

        return [
            'carrier' => is_array($firstSegment) ? ($firstSegment['carrier_display'] ?? null) : null,
            'carrier_logo_url' => is_array($firstSegment) ? ($firstSegment['airline_logo_url'] ?? null) : null,
            'carrier_logo_text' => is_array($firstSegment)
                ? strtoupper(substr((string) ($firstSegment['marketing_carrier'] ?? 'FL'), 0, 2))
                : 'FL',
            'airline_code' => is_array($firstSegment) ? strtoupper((string) ($firstSegment['marketing_carrier'] ?? '')) : null,
            'meal_note' => is_array($firstSegment) ? ($firstSegment['meal_note'] ?? null) : null,
            'route_summary' => ($originCity !== null || $originAirport !== null || $destinationCity !== null || $destinationAirport !== null)
                ? trim(sprintf(
                    '%s to %s',
                    (string) ($originCity ?? $originAirport ?? ''),
                    (string) ($destinationCity ?? $destinationAirport ?? ''),
                ))
                : null,
            'departure_time' => is_array($firstSegment) ? ($firstSegment['departure_time'] ?? null) : null,
            'arrival_time' => is_array($lastSegment) ? ($lastSegment['arrival_time'] ?? null) : null,
            'departure_minutes' => is_array($firstSegment) ? $this->minutesFromTime((string) ($firstSegment['departure_time'] ?? '')) : null,
            'arrival_minutes' => is_array($lastSegment) ? $this->minutesFromTime((string) ($lastSegment['arrival_time'] ?? '')) : null,
            'origin_airport' => is_array($firstSegment) ? ($firstSegment['departure_airport'] ?? null) : null,
            'origin_city' => is_array($firstSegment) ? ($firstSegment['departure_city'] ?? null) : null,
            'destination_airport' => is_array($lastSegment) ? ($lastSegment['arrival_airport'] ?? null) : null,
            'destination_city' => is_array($lastSegment) ? ($lastSegment['arrival_city'] ?? null) : null,
            'duration_minutes' => $journeyMinutes,
            'duration_label' => $this->durationLabel($journeyMinutes),
            'stop_count' => $stopCount,
            'stop_label' => $stopCount === 0 ? 'Non-stop' : ($stopCount === 1 ? '1 stop' : $stopCount.' stops'),
            'baggage_summary' => $offer['baggage_summary'] ?? ($firstSegment['baggage_summary'] ?? null),
            'cabin_class' => $offer['cabin_summary'] ?? ($firstSegment['cabin_class'] ?? null),
            'cabin_class_normalized' => strtolower((string) ($offer['cabin_summary'] ?? ($firstSegment['cabin_class'] ?? ''))),
            'fare_brand' => $offer['fare_brand'] ?? ($firstSegment['fare_brand'] ?? null),
        ];
    }

    /**
     * @return array{city:?string,airport:?string,label:string}
     */
    private function airportMeta(string $airportCode): array
    {
        if ($airportCode === '') {
            return [
                'city' => null,
                'airport' => null,
                'label' => '',
            ];
        }

        $match = $this->airportDirectory->search($airportCode, 1)[0] ?? null;
        if (! is_array($match)) {
            return [
                'city' => $airportCode,
                'airport' => null,
                'label' => $airportCode,
            ];
        }

        return [
            'city' => $match['city'] ?? $airportCode,
            'airport' => $match['airport'] ?? null,
            'label' => $match['label'] ?? $airportCode,
        ];
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function durationMinutes(?CarbonImmutable $start, ?CarbonImmutable $end): ?int
    {
        if ($start === null || $end === null || $end->lessThan($start)) {
            return null;
        }

        return $start->diffInMinutes($end);
    }

    private function durationLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %02dm', $hours, $remainingMinutes);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function carrierDisplayLabel(array $segment): string
    {
        $name = trim((string) ($segment['marketing_carrier_name'] ?? ''));
        $marketing = trim((string) ($segment['marketing_carrier'] ?? ''));
        $operating = trim((string) ($segment['operating_carrier'] ?? ''));

        $headline = $name !== ''
            ? ($marketing !== '' ? $name.' ('.$marketing.')' : $name)
            : ($marketing !== '' ? $marketing : $operating);

        if ($marketing !== '' && $operating !== '' && $marketing !== $operating) {
            return $headline !== ''
                ? $headline.' · Operated by '.$operating
                : 'Operated by '.$operating;
        }

        if ($headline !== '') {
            return $headline;
        }

        return $operating;
    }

    private function airlineLogoUrl(?string $iataCode): ?string
    {
        $code = strtoupper(trim((string) $iataCode));
        if (strlen($code) < 2 || strlen($code) > 3) {
            return null;
        }

        return 'https://images.kiwi.com/airlines/64x64/'.$code.'.png';
    }

    private function minutesFromTime(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }
}
