<?php

namespace App\Integrations\Amadeus\Mappers;

use App\Contracts\Integrations\Mappers\FlightOfferMapperInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Integrations\Amadeus\Support\AmadeusOfferReferenceCodec;

final class AmadeusFlightOfferMapper implements FlightOfferMapperInterface
{
    public function __construct(
        private readonly AmadeusOfferReferenceCodec $codec,
    ) {
    }

    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     * @return list<FlightOfferData>
     */
    public function mapOffers(array $rawSupplierPayload): array
    {
        $rows = $rawSupplierPayload['data'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $offers = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $segments = [];
            $itineraries = is_array($row['itineraries'] ?? null) ? $row['itineraries'] : [];
            foreach ($itineraries as $itinerary) {
                if (! is_array($itinerary)) {
                    continue;
                }
                $itinerarySegments = is_array($itinerary['segments'] ?? null) ? $itinerary['segments'] : [];
                foreach ($itinerarySegments as $segment) {
                    if (! is_array($segment)) {
                        continue;
                    }
                    $segments[] = new FlightSegmentData(
                        departureAirport: strtoupper((string) data_get($segment, 'departure.iataCode', '')),
                        arrivalAirport: strtoupper((string) data_get($segment, 'arrival.iataCode', '')),
                        departureAt: (string) data_get($segment, 'departure.at', ''),
                        arrivalAt: (string) data_get($segment, 'arrival.at', ''),
                        marketingCarrier: isset($segment['carrierCode']) ? (string) $segment['carrierCode'] : null,
                        operatingCarrier: isset($segment['operating']['carrierCode']) ? (string) $segment['operating']['carrierCode'] : null,
                        flightNumber: isset($segment['number']) ? (string) $segment['number'] : null,
                        cabinClass: isset($segment['cabin']) ? (string) $segment['cabin'] : null,
                    );
                }
            }

            $price = $this->mapPrice($row);
            $offers[] = new FlightOfferData(
                id: (string) ($row['id'] ?? uniqid('amadeus-', true)),
                providerOfferReference: $this->codec->encode($row),
                segments: $segments,
                price: $price,
                cabinSummary: isset($segments[0]) ? ($segments[0]->cabinClass ?? null) : null,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'flight_offer'),
            );
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function mapPrice(array $offer): ?PriceBreakdownData
    {
        $price = is_array($offer['price'] ?? null) ? $offer['price'] : [];
        if ($price === []) {
            return null;
        }

        $total = (float) ($price['grandTotal'] ?? 0);
        $base = (float) ($price['base'] ?? 0);
        $tax = max(0.0, $total - $base);

        return new PriceBreakdownData(
            currency: strtoupper((string) ($price['currency'] ?? 'USD')),
            baseAmount: $base,
            taxAmount: $tax,
            feeAmount: 0.0,
            totalAmount: $total,
            status: 'confirmed',
            offerReference: isset($offer['id']) ? (string) $offer['id'] : null,
            lines: [
                ['label' => 'base', 'amount' => $base],
                ['label' => 'tax', 'amount' => $tax],
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }
}
