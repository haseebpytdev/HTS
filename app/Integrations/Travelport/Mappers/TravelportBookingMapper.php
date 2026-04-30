<?php

namespace App\Integrations\Travelport\Mappers;

use App\Contracts\Integrations\Mappers\BookingMapperInterface;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;

/**
 * Maps Travelport booking payloads to {@see BookingData}.
 */
final class TravelportBookingMapper implements BookingMapperInterface
{
    public function providerCode(): string
    {
        return 'travelport';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapBooking(array $rawSupplierPayload): BookingData
    {
        $data = is_array($rawSupplierPayload['BookingResponse'] ?? null)
            ? $rawSupplierPayload['BookingResponse']
            : $rawSupplierPayload;

        $travelers = [];
        foreach ((array) ($data['Travelers'] ?? $data['travelers'] ?? []) as $traveler) {
            if (! is_array($traveler)) {
                continue;
            }
            $travelers[] = new TravelerData(
                travelerType: strtoupper((string) ($traveler['traveler_type'] ?? $traveler['TravelerType'] ?? 'ADULT')),
                givenName: (string) ($traveler['given_name'] ?? $traveler['GivenName'] ?? 'NA'),
                familyName: (string) ($traveler['family_name'] ?? $traveler['Surname'] ?? 'NA'),
                dateOfBirth: isset($traveler['date_of_birth']) ? (string) $traveler['date_of_birth'] : null,
                nationality: isset($traveler['nationality']) ? (string) $traveler['nationality'] : null,
            );
        }

        $price = is_array($data['Price'] ?? null) ? $data['Price'] : [];
        $totalPrice = null;
        if ($price !== []) {
            $total = (float) ($price['TotalPrice'] ?? 0);
            $base = (float) ($price['BasePrice'] ?? 0);
            $tax = max(0.0, (float) ($price['Taxes'] ?? ($total - $base)));
            $totalPrice = new PriceBreakdownData(
                currency: strtoupper((string) ($price['Currency'] ?? 'USD')),
                baseAmount: $base,
                taxAmount: $tax,
                feeAmount: 0.0,
                totalAmount: $total,
                status: 'confirmed',
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        return new BookingData(
            status: strtolower((string) ($data['Status'] ?? $data['status'] ?? 'confirmed')),
            providerCode: $this->providerCode(),
            bookingReference: isset($data['BookingReference']) ? (string) $data['BookingReference'] : null,
            pnr: isset($data['PNR']) ? (string) $data['PNR'] : null,
            travelers: $travelers,
            totalPrice: $totalPrice,
            createdAt: isset($data['CreatedAt']) ? (string) $data['CreatedAt'] : null,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }
}
