<?php

namespace App\Integrations\Sabre\Mappers;

use App\Contracts\Integrations\Mappers\BookingMapperInterface;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;

final class SabreBookingMapper implements BookingMapperInterface
{
    public function providerCode(): string
    {
        return 'sabre';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapBooking(array $rawSupplierPayload): BookingData
    {
        $data = $this->extractData($rawSupplierPayload);
        $travelers = $this->mapTravelers($data);
        $price = $this->extractPrice($data);
        $totalPrice = null;
        if ($price !== []) {
            $total = $this->money($price['totalFare'] ?? 0);
            $base = $this->money($price['baseFare'] ?? 0);
            $tax = max(0.0, $this->money($price['taxes'] ?? ($total - $base)));
            $totalPrice = new PriceBreakdownData(
                currency: strtoupper((string) ($price['currency'] ?? 'USD')),
                baseAmount: $base,
                taxAmount: $tax,
                feeAmount: 0.0,
                totalAmount: $total,
                status: 'confirmed',
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        return new BookingData(
            status: $this->normalizeStatus((string) ($data['status'] ?? data_get($data, 'ApplicationResults.status', 'confirmed'))),
            providerCode: $this->providerCode(),
            bookingReference: $this->firstString([
                $data['bookingReference'] ?? null,
                data_get($data, 'ItineraryRef.ID'),
                data_get($data, 'Reservation.BookingDetails.BookingReferenceID'),
                data_get($data, 'Locator'),
            ]),
            pnr: $this->firstString([
                $data['pnr'] ?? null,
                data_get($data, 'ItineraryRef.@attributes.ID'),
                data_get($data, 'Reservation.BookingDetails.RecordLocator'),
            ]),
            travelers: $travelers,
            totalPrice: $totalPrice,
            createdAt: $this->firstString([
                $data['createdAt'] ?? null,
                data_get($data, 'Ticketing.@attributes.TicketTimeLimit'),
                data_get($data, 'Ticketing.TicketTimeLimit'),
            ]),
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractData(array $raw): array
    {
        if (is_array($raw['CreatePassengerNameRecordRS'] ?? null)) {
            return $raw['CreatePassengerNameRecordRS'];
        }
        if (is_array($raw['GetReservationRS'] ?? null)) {
            return $raw['GetReservationRS'];
        }
        if (is_array($raw['CancelReservationRS'] ?? null)) {
            return $raw['CancelReservationRS'];
        }
        if (is_array($raw['booking'] ?? null)) {
            return $raw['booking'];
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<TravelerData>
     */
    private function mapTravelers(array $data): array
    {
        $rows = $data['travelers'] ?? data_get($data, 'TravelItinerary.CustomerInfo.PersonName', []);
        if (is_array($rows) && isset($rows['GivenName'])) {
            $rows = [$rows];
        }
        if (! is_array($rows)) {
            return [];
        }

        $travelers = [];
        foreach ($rows as $traveler) {
            if (! is_array($traveler)) {
                continue;
            }
            $travelers[] = new TravelerData(
                travelerType: strtoupper((string) ($traveler['traveler_type'] ?? $traveler['PassengerType'] ?? 'ADULT')),
                givenName: (string) ($traveler['given_name'] ?? $traveler['GivenName'] ?? 'NA'),
                familyName: (string) ($traveler['family_name'] ?? $traveler['Surname'] ?? 'NA'),
                dateOfBirth: isset($traveler['date_of_birth']) ? (string) $traveler['date_of_birth'] : null,
                nationality: isset($traveler['nationality']) ? (string) $traveler['nationality'] : null,
            );
        }

        return $travelers;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractPrice(array $data): array
    {
        if (is_array($data['price'] ?? null)) {
            return $data['price'];
        }

        $fare = data_get($data, 'AirItineraryPricingInfo.ItinTotalFare', data_get($data, 'ItineraryPricing.Fare', []));
        if (! is_array($fare)) {
            return [];
        }

        return [
            'currency' => data_get($fare, 'TotalFare.@attributes.CurrencyCode', data_get($fare, 'currency', 'USD')),
            'baseFare' => data_get($fare, 'BaseFare.@attributes.Amount', data_get($fare, 'baseFare', 0)),
            'taxes' => data_get($fare, 'Taxes.@attributes.Amount', data_get($fare, 'taxes', 0)),
            'totalFare' => data_get($fare, 'TotalFare.@attributes.Amount', data_get($fare, 'totalFare', 0)),
        ];
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function normalizeStatus(string $raw): string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return 'confirmed';
        }
        if (str_contains($value, 'cancel')) {
            return 'cancelled';
        }
        if (in_array($value, ['ok', 'success', 'successful'], true)) {
            return 'confirmed';
        }

        return $value;
    }
}
