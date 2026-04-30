<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Duffel\Mappers\DuffelFlightOfferMapper;
use Tests\TestCase;

class DuffelFlightOfferMapperTest extends TestCase
{
    public function test_maps_duffel_offer_payload_to_normalized_offer_data(): void
    {
        $mapper = new DuffelFlightOfferMapper();

        $offers = $mapper->mapOffers([
            'data' => [[
                'type' => 'offer',
                'id' => 'off_001',
                'total_currency' => 'USD',
                'total_amount' => '245.00',
                'base_amount' => '200.00',
                'tax_amount' => '45.00',
                'slices' => [[
                    'segments' => [[
                        'origin' => ['iata_code' => 'KHI'],
                        'destination' => ['iata_code' => 'JED'],
                        'departing_at' => '2026-06-01T08:00:00Z',
                        'arriving_at' => '2026-06-01T11:00:00Z',
                        'marketing_carrier' => ['iata_code' => 'SV'],
                        'operating_carrier' => ['iata_code' => 'SV'],
                        'marketing_carrier_flight_number' => '701',
                        'cabin_class' => 'economy',
                    ]],
                ]],
            ]],
        ]);

        $this->assertCount(1, $offers);
        $this->assertSame('off_001', $offers[0]->id);
        $this->assertSame('off_001', $offers[0]->providerOfferReference);
        $this->assertCount(1, $offers[0]->segments);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
        $this->assertSame('JED', $offers[0]->segments[0]->arrivalAirport);
        $this->assertSame('ECONOMY', $offers[0]->cabinSummary);
        $this->assertNotNull($offers[0]->price);
        $this->assertSame(245.00, $offers[0]->price->totalAmount);
    }
}

