<?php

namespace App\Integrations\Stub;

use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;

final class StubFlightSearchAdapter implements FlightSearchProviderInterface
{
    public function __construct(
        private readonly string $provider = 'stub',
    ) {
    }

    public function providerCode(): string
    {
        return $this->provider;
    }

    public function searchFlights(FlightSearchRequestData $request): array
    {
        /** @var array<string, string> $perProvider */
        $perProvider = config('integrations.stub_provider_scenarios', []);
        $scenario = (string) ($perProvider[$this->providerCode()] ?? config('integrations.stub_scenario', 'flight_search_success'));
        if ($scenario !== 'flight_search_success') {
            $failureFixture = StubFixtureLoader::load($this->providerCode(), $scenario);
            throw StubFailureFactory::fromFixture($this->providerCode(), $failureFixture);
        }

        $fixture = StubFixtureLoader::load($this->providerCode(), 'flight_search_success');
        /** @var list<array<string, mixed>> $offersRaw */
        $offersRaw = $this->extractOffers($fixture);

        $offers = [];
        foreach ($offersRaw as $offerRaw) {
            /** @var list<array<string, mixed>> $segmentsRaw */
            $segmentsRaw = $this->extractSegments($offerRaw);
            $segments = [];
            foreach ($segmentsRaw as $segmentRaw) {
                $segments[] = new FlightSegmentData(
                    departureAirport: (string) ($segmentRaw['departure_airport'] ?? $request->origin),
                    arrivalAirport: (string) ($segmentRaw['arrival_airport'] ?? $request->destination),
                    departureAt: (string) ($segmentRaw['departure_at'] ?? $request->departureDate.'T10:00:00Z'),
                    arrivalAt: (string) ($segmentRaw['arrival_at'] ?? $request->departureDate.'T14:00:00Z'),
                    marketingCarrier: isset($segmentRaw['marketing_carrier']) ? (string) $segmentRaw['marketing_carrier'] : null,
                    operatingCarrier: isset($segmentRaw['operating_carrier']) ? (string) $segmentRaw['operating_carrier'] : null,
                    flightNumber: isset($segmentRaw['flight_number']) ? (string) $segmentRaw['flight_number'] : null,
                    cabinClass: isset($segmentRaw['cabin_class']) ? (string) $segmentRaw['cabin_class'] : null,
                );
            }

            $offers[] = new FlightOfferData(
                id: (string) ($this->offerId($offerRaw) ?? $this->providerCode().'-offer'),
                providerOfferReference: (string) ($this->offerRef($offerRaw) ?? strtoupper($this->providerCode()).'-REF-1'),
                segments: $segments,
                cabinSummary: isset($offerRaw['cabin_summary']) ? (string) $offerRaw['cabin_summary'] : 'ECONOMY',
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'flight_offer'),
            );
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return list<array<string, mixed>>
     */
    private function extractOffers(array $fixture): array
    {
        return match ($this->providerCode()) {
            'travelport' => $fixture['CatalogProductOfferingsResponse']['Offerings'] ?? [],
            'sabre' => $fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'] ?? [],
            'amadeus' => $fixture['data'] ?? [],
            default => $fixture['offers'] ?? [],
        };
    }

    /**
     * @param  array<string, mixed>  $offerRaw
     * @return list<array<string, mixed>>
     */
    private function extractSegments(array $offerRaw): array
    {
        if ($this->providerCode() === 'travelport') {
            $segment = $offerRaw['BestCombinablePrice']['Segments'][0] ?? [];

            return [[
                'departure_airport' => $segment['From'] ?? null,
                'arrival_airport' => $segment['To'] ?? null,
                'departure_at' => $segment['Departure'] ?? null,
                'arrival_at' => $segment['Arrival'] ?? null,
                'marketing_carrier' => $segment['Carrier'] ?? null,
                'flight_number' => $segment['FlightNumber'] ?? null,
                'cabin_class' => $segment['Cabin'] ?? null,
            ]];
        }

        if ($this->providerCode() === 'sabre') {
            $segment = $offerRaw['legs'][0]['segments'][0] ?? [];

            return [[
                'departure_airport' => $segment['departure']['airport'] ?? null,
                'arrival_airport' => $segment['arrival']['airport'] ?? null,
                'departure_at' => $segment['departure']['time'] ?? null,
                'arrival_at' => $segment['arrival']['time'] ?? null,
                'marketing_carrier' => $segment['marketingCarrier'] ?? null,
                'flight_number' => $segment['flightNumber'] ?? null,
                'cabin_class' => $segment['cabin'] ?? null,
            ]];
        }

        if ($this->providerCode() === 'amadeus') {
            $segment = $offerRaw['itineraries'][0]['segments'][0] ?? [];

            return [[
                'departure_airport' => $segment['departure']['iataCode'] ?? null,
                'arrival_airport' => $segment['arrival']['iataCode'] ?? null,
                'departure_at' => $segment['departure']['at'] ?? null,
                'arrival_at' => $segment['arrival']['at'] ?? null,
                'marketing_carrier' => $segment['carrierCode'] ?? null,
                'flight_number' => $segment['number'] ?? null,
                'cabin_class' => $segment['cabin'] ?? null,
            ]];
        }

        return $offerRaw['segments'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $offerRaw
     */
    private function offerId(array $offerRaw): ?string
    {
        return match ($this->providerCode()) {
            'travelport' => isset($offerRaw['id']) ? (string) $offerRaw['id'] : null,
            'sabre' => isset($offerRaw['id']) ? (string) $offerRaw['id'] : null,
            'amadeus' => isset($offerRaw['id']) ? (string) $offerRaw['id'] : null,
            default => isset($offerRaw['id']) ? (string) $offerRaw['id'] : null,
        };
    }

    /**
     * @param  array<string, mixed>  $offerRaw
     */
    private function offerRef(array $offerRaw): ?string
    {
        return match ($this->providerCode()) {
            'travelport' => isset($offerRaw['Identifier']['value']) ? (string) $offerRaw['Identifier']['value'] : null,
            'sabre' => isset($offerRaw['pricingToken']) ? (string) $offerRaw['pricingToken'] : null,
            'amadeus' => isset($offerRaw['id']) ? (string) $offerRaw['id'] : null,
            default => isset($offerRaw['provider_offer_reference']) ? (string) $offerRaw['provider_offer_reference'] : null,
        };
    }
}
