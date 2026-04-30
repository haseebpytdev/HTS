<?php

namespace App\Integrations\Amadeus\Mappers;

use App\Contracts\Integrations\Mappers\BookingMapperInterface;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;

final class AmadeusBookingMapper implements BookingMapperInterface
{
    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapBooking(array $rawSupplierPayload): BookingData
    {
        $data = is_array($rawSupplierPayload['data'] ?? null) ? $rawSupplierPayload['data'] : $rawSupplierPayload;
        $travelers = [];
        foreach ((array) ($data['travelers'] ?? []) as $traveler) {
            if (! is_array($traveler)) {
                continue;
            }
            $travelers[] = new TravelerData(
                travelerType: strtoupper((string) ($traveler['travelerType'] ?? 'ADULT')),
                givenName: (string) data_get($traveler, 'name.firstName', 'NA'),
                familyName: (string) data_get($traveler, 'name.lastName', 'NA'),
                dateOfBirth: isset($traveler['dateOfBirth']) ? (string) $traveler['dateOfBirth'] : null,
                nationality: isset($traveler['nationality']) ? (string) $traveler['nationality'] : null,
            );
        }

        $offer = is_array($data['flightOffers'][0] ?? null) ? $data['flightOffers'][0] : [];
        $price = is_array($offer['price'] ?? null) ? $offer['price'] : [];
        $totalPrice = null;
        if ($price !== []) {
            $total = (float) ($price['grandTotal'] ?? 0);
            $base = (float) ($price['base'] ?? 0);
            $tax = max(0.0, $total - $base);
            $totalPrice = new PriceBreakdownData(
                currency: strtoupper((string) ($price['currency'] ?? 'USD')),
                baseAmount: $base,
                taxAmount: $tax,
                feeAmount: 0.0,
                totalAmount: $total,
                status: 'confirmed',
                offerReference: isset($offer['id']) ? (string) $offer['id'] : null,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        return new BookingData(
            status: strtolower((string) ($data['status'] ?? 'confirmed')),
            providerCode: $this->providerCode(),
            bookingReference: isset($data['id']) ? (string) $data['id'] : null,
            pnr: isset($data['associatedRecords'][0]['reference']) ? (string) $data['associatedRecords'][0]['reference'] : null,
            travelers: $travelers,
            totalPrice: $totalPrice,
            createdAt: isset($data['lastTicketingDate']) ? (string) $data['lastTicketingDate'] : null,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }
}
