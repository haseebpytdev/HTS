<?php

namespace App\Contracts\Integrations\Mappers;

use App\Contracts\Integrations\IdentifiesIntegrationProvider;
use App\Data\Integrations\BookingData;

/**
 * Maps booking create/retrieve/cancel JSON envelopes to {@see BookingData}.
 */
interface BookingMapperInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapBooking(array $rawSupplierPayload): BookingData;
}
