<?php

namespace App\Integrations\Amadeus\Payloads;

use App\Data\Integrations\FlightSearchRequestData;

/**
 * Phase 11.8 — Build Amadeus Flight Offers Search query/body (scaffold).
 */
final class AmadeusFlightSearchPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forSearch(FlightSearchRequestData $request): array
    {
        return [
            'originLocationCode' => strtoupper($request->origin),
            'destinationLocationCode' => strtoupper($request->destination),
            'departureDate' => $request->departureDate,
            'adults' => max(1, $request->adults),
            'children' => max(0, $request->children),
            'infants' => max(0, $request->infants),
            'nonStop' => false,
            'max' => (int) config('amadeus.endpoints.flight_search_max_results', 20),
            'currencyCode' => strtoupper((string) config('amadeus.endpoints.currency', 'USD')),
        ];
    }
}
