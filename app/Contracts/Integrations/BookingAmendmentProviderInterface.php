<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\BookingData;

/**
 * Optional provider contract for explicit booking amendment workflow.
 */
interface BookingAmendmentProviderInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $amendmentPayload
     */
    public function amendBooking(string $bookingReference, array $amendmentPayload = []): BookingData;
}

