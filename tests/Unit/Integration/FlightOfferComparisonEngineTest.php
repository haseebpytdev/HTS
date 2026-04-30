<?php

namespace Tests\Unit\Integration;

use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Services\Integrations\FlightOfferComparisonEngine;
use Tests\TestCase;

class FlightOfferComparisonEngineTest extends TestCase
{
    public function test_it_returns_cheapest_fastest_and_best_buckets(): void
    {
        $engine = new FlightOfferComparisonEngine();

        $fast = $this->offer(
            id: 'fast',
            departureAt: '2026-06-01T08:00:00Z',
            arrivalAt: '2026-06-01T10:00:00Z',
            total: 420.0
        );
        $cheap = $this->offer(
            id: 'cheap',
            departureAt: '2026-06-01T08:00:00Z',
            arrivalAt: '2026-06-01T12:00:00Z',
            total: 300.0
        );

        $comparison = $engine->compare([$fast, $cheap]);

        $this->assertSame('cheap', $comparison['cheapest']['offer_id']);
        $this->assertSame('fast', $comparison['fastest']['offer_id']);
        $this->assertNotNull($comparison['best']);
    }

    private function offer(string $id, string $departureAt, string $arrivalAt, float $total): FlightOfferData
    {
        return new FlightOfferData(
            id: $id,
            providerOfferReference: strtoupper($id),
            segments: [
                new FlightSegmentData(
                    departureAirport: 'KHI',
                    arrivalAirport: 'JED',
                    departureAt: $departureAt,
                    arrivalAt: $arrivalAt,
                    marketingCarrier: 'XY',
                    flightNumber: '101'
                ),
            ],
            price: new PriceBreakdownData(
                currency: 'USD',
                baseAmount: $total - 50.0,
                taxAmount: 50.0,
                feeAmount: 0.0,
                totalAmount: $total,
                status: 'ok',
                offerReference: strtoupper($id),
                metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'price_breakdown')
            ),
            metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'flight_offer')
        );
    }
}
