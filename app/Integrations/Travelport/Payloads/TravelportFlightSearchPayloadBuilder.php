<?php

namespace App\Integrations\Travelport\Payloads;

use App\Data\Integrations\FlightSearchRequestData;

/**
 * Phase 11.6 — Build Travelport-specific air search JSON (scaffold).
 */
final class TravelportFlightSearchPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forSearch(FlightSearchRequestData $request): array
    {
        return [
            'SearchRequest' => [
                'origin' => strtoupper($request->origin),
                'destination' => strtoupper($request->destination),
                'departureDate' => $request->departureDate,
                'passengers' => [
                    'adults' => max(1, $request->adults),
                    'children' => max(0, $request->children),
                    'infants' => max(0, $request->infants),
                ],
                'maxResults' => (int) config('travelport.endpoints.flight_search_max_results', 20),
            ],
        ];
    }
}
