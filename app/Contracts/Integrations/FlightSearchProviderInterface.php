<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSearchRequestData;

/**
 * Normalized flight shopping / availability search.
 */
interface FlightSearchProviderInterface extends IdentifiesIntegrationProvider
{
    /**
     * @return list<FlightOfferData>
     */
    public function searchFlights(FlightSearchRequestData $request): array;
}
