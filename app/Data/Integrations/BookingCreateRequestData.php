<?php

namespace App\Data\Integrations;

/**
 * Internal booking intent passed into {@see \App\Contracts\Integrations\BookingProviderInterface::createBooking()}.
 *
 * @phpstan-type TravelerList list<TravelerData>
 */
final readonly class BookingCreateRequestData
{
    /**
     * @param  list<TravelerData>  $travelers
     */
    public function __construct(
        public string $offerReference,
        public array $travelers,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
    ) {
    }
}
