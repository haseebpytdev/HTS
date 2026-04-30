<?php

namespace Tests\Unit\Integration;

use App\Integrations\Travelport\Mappers\TravelportFlightOfferMapper;
use Tests\TestCase;

class FakeProviderPayloadMapperTest extends TestCase
{
    public function test_travelport_mapper_maps_normalized_fixture_v1_json_file(): void
    {
        $path = base_path('tests/Fixtures/integrations/travelport_normalized_fixture_v1.json');
        $this->assertFileExists($path);

        /** @var array<string, mixed> $raw */
        $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $mapper = new TravelportFlightOfferMapper;
        $offers = $mapper->mapOffers($raw);

        $this->assertCount(1, $offers);
        $this->assertSame('tp-fixture-1', $offers[0]->id);
        $this->assertSame('TP-FIX-REF-1', $offers[0]->providerOfferReference);
        $this->assertCount(1, $offers[0]->segments);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
    }
}
