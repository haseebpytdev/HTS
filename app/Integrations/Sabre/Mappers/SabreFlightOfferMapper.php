<?php

namespace App\Integrations\Sabre\Mappers;

use App\Contracts\Integrations\Mappers\FlightOfferMapperInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class SabreFlightOfferMapper implements FlightOfferMapperInterface
{
    public function providerCode(): string
    {
        return 'sabre';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     * @return list<FlightOfferData>
     */
    public function mapOffers(array $rawSupplierPayload): array
    {
        $itineraries = $this->extractItineraries($rawSupplierPayload);
        $descriptorIndex = $this->extractDescriptorIndex($rawSupplierPayload);

        $offers = [];
        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }

            $segments = $this->mapSegments($itinerary, $descriptorIndex);
            $pricing = $this->extractPricing($itinerary);
            $total = $this->money($pricing['totalFare'] ?? $pricing['grandTotal'] ?? $pricing['total'] ?? 0);
            $base = $this->money($pricing['baseFare'] ?? $pricing['base'] ?? 0);
            $tax = max(0.0, $this->money($pricing['taxes'] ?? $pricing['tax'] ?? ($total - $base)));
            $total = max($total, $base + $tax);
            $currency = strtoupper((string) ($pricing['currency'] ?? 'USD'));
            $providerRef = (string) ($itinerary['pricingToken'] ?? $itinerary['id'] ?? data_get($itinerary, 'SequenceNumber') ?? uniqid('sabre-', true));
            if ($segments === []) {
                continue;
            }

            $offers[] = new FlightOfferData(
                id: (string) ($itinerary['id'] ?? $providerRef),
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
                    metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
                ),
                cabinSummary: $this->summarizeCabins($segments),
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'flight_offer'),
            );
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function extractItineraries(array $raw): array
    {
        $direct = data_get($raw, 'groupedItineraryResponse.itineraryGroups.0.itineraries', $raw['itineraries'] ?? []);
        if (is_array($direct) && $direct !== []) {
            return array_values(array_filter($direct, 'is_array'));
        }

        $soap = data_get($raw, 'OTA_AirLowFareSearchRS.PricedItineraries.PricedItinerary', data_get($raw, 'PricedItineraries.PricedItinerary', $raw['PricedItinerary'] ?? []));
        if (is_array($soap)) {
            if (isset($soap['AirItinerary']) || isset($soap['SequenceNumber'])) {
                return [$soap];
            }

            return array_values(array_filter($soap, 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return list<FlightSegmentData>
     */
    private function mapSegments(array $itinerary, array $descriptorIndex): array
    {
        $segmentsRaw = $this->extractSegmentRows($itinerary, $descriptorIndex);

        $segments = [];
        foreach ($segmentsRaw as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $departureAirport = strtoupper((string) (
                data_get($segment, 'departure.airport')
                ?? data_get($segment, 'DepartureAirport.@attributes.LocationCode')
                ?? ''
            ));
            $arrivalAirport = strtoupper((string) (
                data_get($segment, 'arrival.airport')
                ?? data_get($segment, 'ArrivalAirport.@attributes.LocationCode')
                ?? ''
            ));
            $segments[] = new FlightSegmentData(
                departureAirport: $departureAirport,
                arrivalAirport: $arrivalAirport,
                departureAt: (string) (
                    data_get($segment, 'departure.time')
                    ?? data_get($segment, '@attributes.DepartureDateTime')
                    ?? ''
                ),
                arrivalAt: (string) (
                    data_get($segment, 'arrival.time')
                    ?? data_get($segment, '@attributes.ArrivalDateTime')
                    ?? ''
                ),
                marketingCarrier: isset($segment['marketingCarrier'])
                    ? (string) $segment['marketingCarrier']
                    : (string) (data_get($segment, 'MarketingAirline.@attributes.Code') ?? ''),
                operatingCarrier: isset($segment['operatingCarrier'])
                    ? (string) $segment['operatingCarrier']
                    : (string) (data_get($segment, 'OperatingAirline.@attributes.Code') ?? ''),
                flightNumber: isset($segment['flightNumber'])
                    ? (string) $segment['flightNumber']
                    : (string) (data_get($segment, '@attributes.FlightNumber') ?? ''),
                cabinClass: isset($segment['cabin']) ? (string) $segment['cabin'] : null,
            );
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<int, array<string, mixed>>  $descriptorIndex
     * @return list<array<string, mixed>>
     */
    private function extractSegmentRows(array $itinerary, array $descriptorIndex): array
    {
        $rows = [];
        $legs = data_get($itinerary, 'legs', []);
        if (is_array($legs) && $legs !== []) {
            foreach ($legs as $leg) {
                if (! is_array($leg)) {
                    continue;
                }
                $segments = $leg['segments'] ?? [];
                if (is_array($segments)) {
                    foreach ($segments as $segment) {
                        if (is_array($segment)) {
                            $rows[] = $segment;
                            continue;
                        }
                        if (is_numeric($segment)) {
                            $descriptor = $descriptorIndex[(int) $segment] ?? null;
                            if (is_array($descriptor)) {
                                $rows[] = $descriptor;
                            }
                        }
                    }
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $options = data_get($itinerary, 'AirItinerary.OriginDestinationOptions.OriginDestinationOption', []);
        if (is_array($options) && isset($options['FlightSegment'])) {
            $options = [$options];
        }
        if (! is_array($options)) {
            return [];
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $segments = $option['FlightSegment'] ?? [];
            if (is_array($segments) && isset($segments['DepartureAirport'])) {
                $segments = [$segments];
            }
            if (! is_array($segments)) {
                continue;
            }
            foreach ($segments as $segment) {
                if (is_array($segment)) {
                    $rows[] = $segment;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function extractDescriptorIndex(array $raw): array
    {
        $index = [];
        $scheduleDescs = data_get($raw, 'groupedItineraryResponse.scheduleDescs', []);
        if (! is_array($scheduleDescs)) {
            return $index;
        }

        foreach ($scheduleDescs as $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }

            $id = (int) ($descriptor['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $departure = $descriptor['departure'] ?? [];
            $arrival = $descriptor['arrival'] ?? [];
            if (! is_array($departure) || ! is_array($arrival)) {
                continue;
            }

            $carrier = $descriptor['carrier'] ?? [];
            $index[$id] = [
                'departure' => [
                    'airport' => (string) ($departure['airport'] ?? ''),
                    'time' => (string) ($departure['time'] ?? ''),
                ],
                'arrival' => [
                    'airport' => (string) ($arrival['airport'] ?? ''),
                    'time' => (string) ($arrival['time'] ?? ''),
                ],
                'marketingCarrier' => (string) ($carrier['marketing'] ?? ''),
                'operatingCarrier' => (string) ($carrier['operating'] ?? $carrier['marketing'] ?? ''),
                'flightNumber' => (string) ($descriptor['flightNumber'] ?? ''),
            ];
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return array<string, mixed>
     */
    private function extractPricing(array $itinerary): array
    {
        $pricing = is_array($itinerary['pricingInformation'][0]['fare'] ?? null) ? $itinerary['pricingInformation'][0]['fare'] : [];
        if ($pricing !== []) {
            return $pricing;
        }

        $soapPricing = data_get($itinerary, 'AirItineraryPricingInfo.ItinTotalFare', []);
        if (! is_array($soapPricing)) {
            return [];
        }

        return [
            'currency' => data_get($soapPricing, 'TotalFare.@attributes.CurrencyCode', data_get($soapPricing, 'BaseFare.@attributes.CurrencyCode', 'USD')),
            'baseFare' => data_get($soapPricing, 'BaseFare.@attributes.Amount', data_get($soapPricing, 'BaseFare.Amount', 0)),
            'taxes' => data_get($soapPricing, 'Taxes.@attributes.Amount', data_get($soapPricing, 'Taxes.Amount', 0)),
            'totalFare' => data_get($soapPricing, 'TotalFare.@attributes.Amount', data_get($soapPricing, 'TotalFare.Amount', 0)),
        ];
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    /**
     * @param  list<FlightSegmentData>  $segments
     */
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
        if (count($cabins) === 1) {
            return $cabins[0];
        }

        return 'MIXED';
    }
}
