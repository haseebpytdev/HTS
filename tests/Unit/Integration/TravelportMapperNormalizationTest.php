<?php

namespace Tests\Unit\Integration;

use App\Integrations\Travelport\Mappers\TravelportBookingMapper;
use App\Integrations\Travelport\Mappers\TravelportFlightOfferMapper;
use App\Integrations\Travelport\Mappers\TravelportPriceBreakdownMapper;
use Tests\TestCase;

class TravelportMapperNormalizationTest extends TestCase
{
    public function test_maps_realistic_travelport_offer_payload(): void
    {
        $mapper = new TravelportFlightOfferMapper;
        $offers = $mapper->mapOffers([
            'CatalogProductOfferingsResponse' => [
                'Offerings' => [[
                    'id' => 'tp-1',
                    'Identifier' => ['value' => 'TP-REF-1'],
                    'BestCombinablePrice' => [
                        'Currency' => 'USD',
                        'BasePrice' => 100,
                        'Taxes' => 20,
                        'TotalPrice' => 120,
                        'Segments' => [[
                            'From' => 'KHI',
                            'To' => 'JED',
                            'Departure' => '2026-07-01T10:00:00',
                            'Arrival' => '2026-07-01T13:00:00',
                            'Carrier' => 'PK',
                            'FlightNumber' => '701',
                            'Cabin' => 'ECONOMY',
                        ]],
                    ],
                ]],
            ],
        ]);

        $this->assertCount(1, $offers);
        $this->assertSame('TP-REF-1', $offers[0]->providerOfferReference);
        $this->assertSame(120.0, $offers[0]->price?->totalAmount);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
    }

    public function test_maps_travelport_price_payload(): void
    {
        $mapper = new TravelportPriceBreakdownMapper;
        $price = $mapper->mapPriceBreakdown([
            'PriceResponse' => [
                'Currency' => 'USD',
                'BasePrice' => 140,
                'Taxes' => 30,
                'TotalPrice' => 170,
                'OfferReference' => 'TP-REF-2',
            ],
        ]);

        $this->assertSame('confirmed', $price->status);
        $this->assertSame(170.0, $price->totalAmount);
        $this->assertSame('TP-REF-2', $price->offerReference);
    }

    public function test_maps_travelport_booking_payload(): void
    {
        $mapper = new TravelportBookingMapper;
        $booking = $mapper->mapBooking([
            'BookingResponse' => [
                'Status' => 'CONFIRMED',
                'BookingReference' => 'TP-BKG-1',
                'PNR' => 'ABC123',
                'Travelers' => [[
                    'TravelerType' => 'ADULT',
                    'GivenName' => 'John',
                    'Surname' => 'Doe',
                ]],
                'Price' => [
                    'Currency' => 'USD',
                    'BasePrice' => 200,
                    'Taxes' => 50,
                    'TotalPrice' => 250,
                ],
            ],
        ]);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('TP-BKG-1', $booking->bookingReference);
        $this->assertSame('ABC123', $booking->pnr);
        $this->assertCount(1, $booking->travelers);
        $this->assertSame(250.0, $booking->totalPrice?->totalAmount);
    }
}

