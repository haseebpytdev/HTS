<?php

namespace Tests\Unit\Integration;

use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Amadeus\Payloads\AmadeusFlightSearchPayloadBuilder;
use Tests\TestCase;

class AmadeusPayloadBuilderTest extends TestCase
{
    public function test_builds_flight_search_query_payload(): void
    {
        config([
            'amadeus.endpoints.flight_search_max_results' => 15,
            'amadeus.endpoints.currency' => 'PKR',
        ]);

        $builder = new AmadeusFlightSearchPayloadBuilder;
        $payload = $builder->forSearch(new FlightSearchRequestData(
            origin: 'khi',
            destination: 'jed',
            departureDate: '2026-06-01',
            adults: 2,
            children: 1,
            infants: 0,
        ));

        $this->assertSame('KHI', $payload['originLocationCode']);
        $this->assertSame('JED', $payload['destinationLocationCode']);
        $this->assertSame('2026-06-01', $payload['departureDate']);
        $this->assertSame(2, $payload['adults']);
        $this->assertSame(1, $payload['children']);
        $this->assertSame(0, $payload['infants']);
        $this->assertSame(15, $payload['max']);
        $this->assertSame('PKR', $payload['currencyCode']);
    }
}

