<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;

/**
 * Booking lifecycle: create, retrieve, cancel. Ancillary/seat map can extend later.
 */
interface BookingProviderInterface extends IdentifiesIntegrationProvider
{
    public function createBooking(BookingCreateRequestData $request): BookingData;

    public function retrieveBooking(string $bookingReference): BookingData;

    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData;
}
