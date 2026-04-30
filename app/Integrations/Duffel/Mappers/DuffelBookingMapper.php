<?php

namespace App\Integrations\Duffel\Mappers;

use App\Contracts\Integrations\Mappers\BookingMapperInterface;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;

final class DuffelBookingMapper implements BookingMapperInterface
{
    public function providerCode(): string
    {
        return 'duffel';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapBooking(array $rawSupplierPayload): BookingData
    {
        $order = $this->extractOrder($rawSupplierPayload);
        $travelers = $this->mapTravelers($order);
        $totalPrice = $this->mapPrice($order);

        return new BookingData(
            status: $this->normalizeStatus((string) ($order['status'] ?? '')),
            providerCode: $this->providerCode(),
            bookingReference: $this->firstString([
                $order['id'] ?? null,
                $order['booking_reference'] ?? null,
            ]),
            pnr: $this->firstString([
                $order['booking_reference'] ?? null,
                data_get($order, 'metadata.pnr'),
                data_get($order, 'slices.0.segments.0.operating_carrier_pnr'),
                data_get($order, 'slices.0.segments.0.marketing_carrier_pnr'),
            ]),
            travelers: $travelers,
            totalPrice: $totalPrice,
            createdAt: isset($order['created_at']) ? (string) $order['created_at'] : null,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractOrder(array $raw): array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        if (is_array($data) && strtolower((string) ($data['type'] ?? 'order')) === 'order') {
            return $data;
        }

        if (is_array($raw['order'] ?? null)) {
            return $raw['order'];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<TravelerData>
     */
    private function mapTravelers(array $order): array
    {
        $rows = is_array($order['passengers'] ?? null) ? $order['passengers'] : [];
        $travelers = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $travelers[] = new TravelerData(
                travelerType: strtoupper((string) ($row['type'] ?? 'adult')),
                givenName: (string) ($row['given_name'] ?? 'NA'),
                familyName: (string) ($row['family_name'] ?? 'NA'),
                dateOfBirth: isset($row['born_on']) ? (string) $row['born_on'] : null,
                nationality: isset($row['nationality']) ? strtoupper((string) $row['nationality']) : null,
            );
        }

        return $travelers;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function mapPrice(array $order): ?PriceBreakdownData
    {
        $currency = strtoupper((string) ($order['total_currency'] ?? $order['currency'] ?? ''));
        $totalRaw = $order['total_amount'] ?? null;
        if ($currency === '' || $totalRaw === null) {
            return null;
        }

        $total = $this->money($totalRaw);
        $base = $this->money($order['base_amount'] ?? $total);
        $tax = $this->money($order['tax_amount'] ?? max(0.0, $total - $base));
        $fee = $this->money($order['fee_amount'] ?? 0);

        return new PriceBreakdownData(
            currency: $currency,
            baseAmount: $base,
            taxAmount: max(0.0, $tax),
            feeAmount: max(0.0, $fee),
            totalAmount: max(0.0, $total),
            status: 'confirmed',
            offerReference: isset($order['id']) ? (string) $order['id'] : null,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
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

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return 'confirmed';
        }

        return match ($normalized) {
            'awaiting_payment', 'pending', 'on_hold' => 'pending',
            'confirmed', 'ticketed' => 'confirmed',
            'cancellation_pending', 'cancel_requested', 'cancellation_requested' => 'cancel_pending',
            'cancelled', 'expired', 'failed', 'rejected' => 'cancelled',
            default => $normalized,
        };
    }
}
