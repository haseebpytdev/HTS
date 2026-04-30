<?php

namespace App\Integrations\Duffel\Mappers;

use App\Contracts\Integrations\Mappers\FlightOfferMapperInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class DuffelFlightOfferMapper implements FlightOfferMapperInterface
{
    public function providerCode(): string
    {
        return 'duffel';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     * @return list<FlightOfferData>
     */
    public function mapOffers(array $rawSupplierPayload): array
    {
        $offers = [];
        foreach ($this->extractOfferRows($rawSupplierPayload) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $segments = $this->mapSegments($row);
            if ($segments === []) {
                continue;
            }

            $id = (string) ($row['id'] ?? uniqid('duffel-', true));
            $price = $this->mapPrice($row, $id);

            $offers[] = new FlightOfferData(
                id: $id,
                providerOfferReference: $id,
                segments: $segments,
                price: $price,
                cabinSummary: $this->summarizeCabins($segments),
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'flight_offer'),
            );
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<FlightSegmentData>
     */
    private function mapSegments(array $row): array
    {
        $segments = [];
        $slices = is_array($row['slices'] ?? null) ? $row['slices'] : [];
        foreach ($slices as $slice) {
            if (! is_array($slice)) {
                continue;
            }
            $sliceSegments = is_array($slice['segments'] ?? null) ? $slice['segments'] : [];
            foreach ($sliceSegments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $segments[] = new FlightSegmentData(
                    departureAirport: strtoupper((string) data_get($segment, 'origin.iata_code', data_get($segment, 'origin', ''))),
                    arrivalAirport: strtoupper((string) data_get($segment, 'destination.iata_code', data_get($segment, 'destination', ''))),
                    departureAt: (string) data_get($segment, 'departing_at', ''),
                    arrivalAt: (string) data_get($segment, 'arriving_at', ''),
                    marketingCarrier: (string) data_get($segment, 'marketing_carrier.iata_code', data_get($segment, 'marketing_carrier', '')) ?: null,
                    operatingCarrier: (string) data_get($segment, 'operating_carrier.iata_code', data_get($segment, 'operating_carrier', '')) ?: null,
                    flightNumber: (string) data_get($segment, 'marketing_carrier_flight_number', data_get($segment, 'flight_number', '')) ?: null,
                    cabinClass: (string) data_get($segment, 'cabin_class', '') ?: null,
                    marketingCarrierName: $this->segmentMarketingCarrierName($segment),
                    baggageSummary: $this->segmentBaggageSummary($segment),
                    mealNote: $this->segmentMealNote($segment),
                );
            }
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapPrice(array $row, string $offerId): ?PriceBreakdownData
    {
        $currency = strtoupper((string) ($row['total_currency'] ?? $row['currency'] ?? 'USD'));
        $total = $this->money($row['total_amount'] ?? 0);
        $base = $this->money($row['base_amount'] ?? 0);
        $tax = $this->money($row['tax_amount'] ?? max(0.0, $total - $base));
        $fee = $this->money($row['fee_amount'] ?? 0);

        if ($total <= 0 && $base <= 0 && $tax <= 0 && $fee <= 0) {
            return null;
        }

        return new PriceBreakdownData(
            currency: $currency,
            baseAmount: $base,
            taxAmount: max(0.0, $tax),
            feeAmount: max(0.0, $fee),
            totalAmount: max(0.0, $total),
            status: 'confirmed',
            offerReference: $offerId,
            lines: [
                ['label' => 'base', 'amount' => $base],
                ['label' => 'tax', 'amount' => max(0.0, $tax)],
                ['label' => 'fee', 'amount' => max(0.0, $fee)],
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function extractOfferRows(array $raw): array
    {
        $rows = data_get($raw, 'data.offers', $raw['offers'] ?? null);
        if (! is_array($rows) || $rows === []) {
            $rows = $raw['data'] ?? [];
        }

        if (! is_array($rows)) {
            return [];
        }

        // Duffel responses may include non-offer entities under data; keep only offer rows.
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower((string) ($row['type'] ?? 'offer'));
            if ($type !== '' && $type !== 'offer') {
                continue;
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
    }

    /**
     * @param  list<FlightSegmentData>  $segments
     */
    /**
     * @param  array<string, mixed>  $segment
     */
    private function segmentMarketingCarrierName(array $segment): ?string
    {
        $name = trim((string) data_get($segment, 'marketing_carrier.name'));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function segmentBaggageSummary(array $segment): ?string
    {
        $parts = [];
        foreach ((array) data_get($segment, 'passengers', []) as $passenger) {
            if (! is_array($passenger)) {
                continue;
            }
            foreach ((array) data_get($passenger, 'cabin_baggages', []) as $bag) {
                if (! is_array($bag)) {
                    continue;
                }
                $q = $bag['quantity'] ?? null;
                $type = trim((string) ($bag['bag_type'] ?? $bag['type'] ?? ''));
                $chunk = trim(implode(' ', array_filter([is_numeric($q) ? (string) $q.'×' : null, $type])));
                if ($chunk !== '') {
                    $parts[] = $chunk;
                }
            }
            foreach ((array) data_get($passenger, 'baggages', []) as $bag) {
                if (! is_array($bag)) {
                    continue;
                }
                $type = trim((string) ($bag['type'] ?? ''));
                $qty = $bag['quantity'] ?? null;
                $chunk = trim(implode(' ', array_filter([is_numeric($qty) ? (string) $qty.'×' : null, $type])));
                if ($chunk !== '') {
                    $parts[] = $chunk;
                }
            }
        }

        if ($parts === []) {
            $fallback = trim((string) data_get($segment, 'baggages'));
            if ($fallback !== '') {
                return $fallback;
            }

            return null;
        }

        return implode(' · ', array_values(array_unique($parts)));
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function segmentMealNote(array $segment): ?string
    {
        foreach (['meal', 'meal_service', 'meal_description'] as $key) {
            $v = data_get($segment, $key);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        $amenities = (array) data_get($segment, 'passengers.0.amenities', []);
        foreach ($amenities as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! str_contains(strtolower((string) ($item['type'] ?? '')), 'meal')) {
                continue;
            }
            $d = trim((string) ($item['description'] ?? ''));
            if ($d !== '') {
                return $d;
            }
        }

        return null;
    }

    private function summarizeCabins(array $segments): ?string
    {
        $cabins = [];
        foreach ($segments as $segment) {
            if ($segment->cabinClass !== null && $segment->cabinClass !== '') {
                $cabins[] = strtoupper($segment->cabinClass);
            }
        }

        $cabins = array_values(array_unique($cabins));
        if ($cabins === []) {
            return null;
        }

        return count($cabins) === 1 ? $cabins[0] : 'MIXED';
    }
}

