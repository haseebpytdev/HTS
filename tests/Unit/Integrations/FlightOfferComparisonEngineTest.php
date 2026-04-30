<?php

namespace Tests\Unit\Integrations;

use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Stub\StubFlightSearchAdapter;
use App\Services\Integrations\FlightOfferComparisonEngine;
use Tests\TestCase;

class FlightOfferComparisonEngineTest extends TestCase
{
    public function test_comparison_buckets_are_generated_from_fixture_driven_offers(): void
    {
        config(['integrations.stub_scenario' => 'flight_search_success']);

        $request = new FlightSearchRequestData('KHI', 'JED', '2026-06-01');

        $travelport = (new StubFlightSearchAdapter('travelport'))->searchFlights($request);
        $sabre = (new StubFlightSearchAdapter('sabre'))->searchFlights($request);
        $amadeus = (new StubFlightSearchAdapter('amadeus'))->searchFlights($request);

        $offers = array_merge($travelport, $sabre, $amadeus);
        $comparison = (new FlightOfferComparisonEngine())->compare($offers);

        $this->assertNotNull($comparison['cheapest']);
        $this->assertNotNull($comparison['fastest']);
        $this->assertNotNull($comparison['best']);
        $this->assertArrayHasKey('offer_id', $comparison['cheapest']);
        $this->assertArrayHasKey('offer_id', $comparison['fastest']);
        $this->assertArrayHasKey('offer_id', $comparison['best']);
    }
}
