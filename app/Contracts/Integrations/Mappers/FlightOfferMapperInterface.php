<?php

namespace App\Contracts\Integrations\Mappers;

use App\Contracts\Integrations\IdentifiesIntegrationProvider;
use App\Data\Integrations\FlightOfferData;

/**
 * Maps a vendor-specific search (or offer list) JSON payload to normalized offers.
 */
interface FlightOfferMapperInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     * @return list<FlightOfferData>
     */
    public function mapOffers(array $rawSupplierPayload): array;
}
