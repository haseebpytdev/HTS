<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\BookingData;

/**
 * Optional provider contract for explicit booking ticketing workflow.
 */
interface BookingTicketingProviderInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    public function ticketBooking(string $bookingReference, array $opaqueContext = []): BookingData;
}

