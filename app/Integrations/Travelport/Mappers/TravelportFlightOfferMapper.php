<?php

namespace App\Integrations\Travelport\Mappers;

use App\Contracts\Integrations\Mappers\FlightOfferMapperInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

/**
 * Maps Travelport search/offer payloads to normalized FlightOfferData (scaffold + test fixtures).
 */
final class TravelportFlightOfferMapper implements FlightOfferMapperInterface
{
    public function providerCode(): string
    {
        return 'travelport';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     * @return list<FlightOfferData>
     */
    public function mapOffers(array $rawSupplierPayload): array
    {
        if (($rawSupplierPayload['_normalized_fixture_v1'] ?? false) === true) {
            return $this->mapNormalizedFixtureV1($rawSupplierPayload);
        }

        return $this->mapCatalogOfferings($rawSupplierPayload);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<FlightOfferData>
     */
    private function mapNormalizedFixtureV1(array $raw): array
    {
        $out = [];
        $offers = $raw['offers'] ?? [];
        if (! is_array($offers)) {
            return [];
        }

        foreach ($offers as $row) {
            if (! is_array($row)) {
                continue;
            }

            $segments = [];
            foreach ($row['segments'] ?? [] as $seg) {
                if (! is_array($seg)) {
                    continue;
                }

                $segments[] = new FlightSegmentData(
                    departureAirport: (string) ($seg['departure_airport'] ?? ''),
                    arrivalAirport: (string) ($seg['arrival_airport'] ?? ''),
                    departureAt: (string) ($seg['departure_at'] ?? ''),
                    arrivalAt: (string) ($seg['arrival_at'] ?? ''),
                    marketingCarrier: isset($seg['marketing_carrier']) ? (string) $seg['marketing_carrier'] : null,
                    operatingCarrier: isset($seg['operating_carrier']) ? (string) $seg['operating_carrier'] : null,
                    flightNumber: isset($seg['flight_number']) ? (string) $seg['flight_number'] : null,
                    cabinClass: isset($seg['cabin_class']) ? (string) $seg['cabin_class'] : null,
                );
            }

            $out[] = new FlightOfferData(
                id: (string) ($row['id'] ?? ''),
                providerOfferReference: (string) ($row['provider_offer_reference'] ?? ''),
                segments: $segments,
                cabinSummary: isset($row['cabin_summary']) ? (string) $row['cabin_summary'] : null,
                metadata: NormalizedPayloadMetadata::forSchemaKey('travelport', 'flight_offer'),
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<FlightOfferData>
     */
    private function mapCatalogOfferings(array $raw): array
    {
        $offers = data_get($raw, 'CatalogProductOfferingsResponse.Offerings', $raw['Offerings'] ?? []);
        if (! is_array($offers)) {
            return [];
        }

        $out = [];
        foreach ($offers as $row) {
            if (! is_array($row)) {
                continue;
            }

            $best = is_array($row['BestCombinablePrice'] ?? null) ? $row['BestCombinablePrice'] : [];
            $segmentsRaw = is_array($best['Segments'] ?? null) ? $best['Segments'] : [];
            $segments = [];
            foreach ($segmentsRaw as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $segments[] = new FlightSegmentData(
                    departureAirport: strtoupper((string) ($segment['From'] ?? '')),
                    arrivalAirport: strtoupper((string) ($segment['To'] ?? '')),
                    departureAt: (string) ($segment['Departure'] ?? ''),
                    arrivalAt: (string) ($segment['Arrival'] ?? ''),
                    marketingCarrier: isset($segment['Carrier']) ? (string) $segment['Carrier'] : null,
                    operatingCarrier: isset($segment['Carrier']) ? (string) $segment['Carrier'] : null,
                    flightNumber: isset($segment['FlightNumber']) ? (string) $segment['FlightNumber'] : null,
                    cabinClass: isset($segment['Cabin']) ? (string) $segment['Cabin'] : null,
                );
            }

            $total = (float) ($best['TotalPrice'] ?? 0);
            $base = (float) ($best['BasePrice'] ?? 0);
            $tax = max(0.0, (float) ($best['Taxes'] ?? ($total - $base)));
            $currency = strtoupper((string) ($best['Currency'] ?? 'USD'));
            $providerRef = (string) data_get($row, 'Identifier.value', ($row['id'] ?? uniqid('tp-', true)));

            $out[] = new FlightOfferData(
                id: (string) ($row['id'] ?? $providerRef),
                providerOfferReference: $providerRef,
                segments: $segments,
                price: new PriceBreakdownData(
                    currency: $currency,
                    baseAmount: $base,
                    taxAmount: $tax,
                    feeAmount: 0.0,
                    totalAmount: $total,
                    status: 'confirmed',
                    offerReference: $providerRef,
                    lines: [
                        ['label' => 'base', 'amount' => $base],
                        ['label' => 'tax', 'amount' => $tax],
                    ],
                    metadata: NormalizedPayloadMetadata::forSchemaKey('travelport', 'price_breakdown'),
                ),
                cabinSummary: isset($segments[0]) ? ($segments[0]->cabinClass ?? null) : null,
                metadata: NormalizedPayloadMetadata::forSchemaKey('travelport', 'flight_offer'),
            );
        }

        return $out;
    }
}
