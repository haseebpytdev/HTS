<?php

namespace App\Integrations\Duffel\Payloads;

use App\Data\Integrations\FlightSearchRequestData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class DuffelOfferRequestPayloadBuilder
{
    /** @var list<string> */
    private const DUFFEL_CABIN_CLASSES = ['first', 'business', 'premium_economy', 'economy'];

    /**
     * Duffel search requires numeric ages for travellers under 18 (not `type: child`).
     * @see https://duffel.com/docs/guides/getting-started-with-flights
     */
    private const DEFAULT_CHILD_SEARCH_AGE = 10;

    /**
     * Lap-infant style search when only a count is known (Duffel examples use `age` for infants).
     */
    private const DEFAULT_INFANT_SEARCH_AGE = 1;

    /**
     * @return array<string, mixed>
     */
    public function forSearch(FlightSearchRequestData $request): array
    {
        $origin = $this->normalizeIataAirportCode($request->origin);
        $destination = $this->normalizeIataAirportCode($request->destination);
        $departureDate = $this->normalizeDepartureDateYmd($request->departureDate);
        $cabinClass = $this->resolveCabinClass($request->cabinClass);

        $maxConnections = (int) config('duffel.search.max_connections', 1);
        $maxConnections = max(0, min(2, $maxConnections));

        $data = [
            'slices' => [[
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
            ]],
            'passengers' => $this->passengers($request),
            'max_connections' => $maxConnections,
        ];

        if ($cabinClass !== null) {
            $data['cabin_class'] = $cabinClass;
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * Duffel expects IATA airport or city codes; enforce 3-letter codes for airport search.
     */
    private function normalizeIataAirportCode(string $code): string
    {
        $trimmed = strtoupper(preg_replace('/\s+/', '', $code) ?? $code);
        if (preg_match('/^[A-Z]{3}$/', $trimmed) === 1) {
            return $trimmed;
        }

        return strlen($trimmed) >= 3 ? substr($trimmed, 0, 3) : $trimmed;
    }

    /**
     * Ensure Duffel receives a calendar date as Y-m-d (API: slice.departure_date).
     */
    private function normalizeDepartureDateYmd(string $date): string
    {
        $trimmed = trim($date);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $trimmed, $m) === 1) {
            return $m[1];
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    private function resolveCabinClass(?string $fromRequest): ?string
    {
        $candidates = [$fromRequest, (string) config('duffel.search.cabin_class', '')];
        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                continue;
            }
            if (in_array($normalized, self::DUFFEL_CABIN_CLASSES, true)) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function passengers(FlightSearchRequestData $request): array
    {
        $adults = max(1, $request->adults);
        $children = max(0, $request->children);
        $infants = max(0, $request->infants);

        if ($infants > $adults) {
            Log::warning('integrations.duffel.offer_request.passengers_adjusted', [
                'reason' => 'infants_exceed_adults',
                'adults' => $adults,
                'infants_requested' => $infants,
                'infants_used' => $adults,
            ]);
            $infants = $adults;
        }

        $passengers = [];
        for ($i = 0; $i < $adults; $i++) {
            $passengers[] = ['type' => 'adult'];
        }
        for ($i = 0; $i < $children; $i++) {
            $passengers[] = ['age' => self::DEFAULT_CHILD_SEARCH_AGE];
        }
        for ($i = 0; $i < $infants; $i++) {
            $passengers[] = ['age' => self::DEFAULT_INFANT_SEARCH_AGE];
        }

        return $passengers;
    }
}
