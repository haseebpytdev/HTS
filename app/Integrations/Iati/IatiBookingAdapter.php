<?php

namespace App\Integrations\Iati;

use App\Contracts\Integrations\BookingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;

final class IatiBookingAdapter implements BookingProviderInterface
{
    public function __construct(
        private readonly IatiClient $client,
    ) {
    }

    public function providerCode(): string
    {
        return 'iati';
    }

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        $this->client->baseUrl();

        return new BookingData(
            status: 'not_implemented',
            providerCode: $this->providerCode(),
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function retrieveBooking(string $bookingReference): BookingData
    {
        return new BookingData(
            status: 'not_found',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        return new BookingData(
            status: 'not_implemented',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }
}
