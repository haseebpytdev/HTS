<?php

namespace Tests\Unit\Integration;

use App\Integrations\Amadeus\Mappers\AmadeusFlightOfferMapper;
use App\Integrations\Amadeus\Support\AmadeusOfferReferenceCodec;
use Tests\TestCase;

class AmadeusFlightOfferMapperTest extends TestCase
{
    public function test_maps_amadeus_offer_payload_to_normalized_offer_data(): void
    {
        $mapper = new AmadeusFlightOfferMapper(new AmadeusOfferReferenceCodec);
        $offers = $mapper->mapOffers([
            'data' => [[
                'id' => 'A1',
                'price' => [
                    'currency' => 'USD',
                    'base' => '120.00',
                    'grandTotal' => '145.50',
                ],
                'itineraries' => [[
                    'segments' => [[
                        'departure' => ['iataCode' => 'KHI', 'at' => '2026-06-01T08:00:00'],
                        'arrival' => ['iataCode' => 'JED', 'at' => '2026-06-01T11:00:00'],
                        'carrierCode' => 'SV',
                        'number' => '701',
                    ]],
                ]],
            ]],
        ]);

        $this->assertCount(1, $offers);
        $this->assertSame('A1', $offers[0]->id);
        $this->assertStringStartsWith('amadeus:', $offers[0]->providerOfferReference);
        $this->assertCount(1, $offers[0]->segments);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
        $this->assertSame('JED', $offers[0]->segments[0]->arrivalAirport);
        $this->assertNotNull($offers[0]->price);
        $this->assertSame(145.50, $offers[0]->price->totalAmount);
    }
}

