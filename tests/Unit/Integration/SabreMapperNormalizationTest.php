<?php

namespace Tests\Unit\Integration;

use App\Integrations\Sabre\Mappers\SabreBookingMapper;
use App\Integrations\Sabre\Mappers\SabreFlightOfferMapper;
use App\Integrations\Sabre\Mappers\SabrePriceBreakdownMapper;
use App\Integrations\Sabre\Payloads\SabreFlightSearchPayloadBuilder;
use App\Data\Integrations\FlightSearchRequestData;
use Tests\TestCase;

class SabreMapperNormalizationTest extends TestCase
{
    public function test_maps_realistic_sabre_offer_payload(): void
    {
        $mapper = new SabreFlightOfferMapper;
        $offers = $mapper->mapOffers([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [[
                    'itineraries' => [[
                        'id' => 's1',
                        'pricingToken' => 'SABRE-TOKEN-1',
                        'pricingInformation' => [[
                            'fare' => [
                                'currency' => 'USD',
                                'baseFare' => 110,
                                'taxes' => 25,
                                'totalFare' => 135,
                            ],
                        ]],
                        'legs' => [[
                            'segments' => [[
                                'departure' => ['airport' => 'KHI', 'time' => '2026-08-01T08:00:00'],
                                'arrival' => ['airport' => 'JED', 'time' => '2026-08-01T11:00:00'],
                                'marketingCarrier' => 'SV',
                                'flightNumber' => '700',
                                'cabin' => 'ECONOMY',
                            ]],
                        ]],
                    ]],
                ]],
            ],
        ]);

        $this->assertCount(1, $offers);
        $this->assertSame('SABRE-TOKEN-1', $offers[0]->providerOfferReference);
        $this->assertSame(135.0, $offers[0]->price?->totalAmount);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
    }

    public function test_maps_sabre_price_payload(): void
    {
        $mapper = new SabrePriceBreakdownMapper;
        $price = $mapper->mapPriceBreakdown([
            'OTA_AirPriceRS' => [
                'currency' => 'USD',
                'baseFare' => 150,
                'taxes' => 35,
                'totalFare' => 185,
                'pricingToken' => 'SABRE-TOKEN-2',
            ],
        ]);

        $this->assertSame('confirmed', $price->status);
        $this->assertSame(185.0, $price->totalAmount);
        $this->assertSame('SABRE-TOKEN-2', $price->offerReference);
    }

    public function test_maps_sabre_booking_payload(): void
    {
        $mapper = new SabreBookingMapper;
        $booking = $mapper->mapBooking([
            'booking' => [
                'status' => 'CONFIRMED',
                'bookingReference' => 'SB-BKG-1',
                'pnr' => 'XYZ789',
                'travelers' => [[
                    'traveler_type' => 'ADULT',
                    'given_name' => 'Jane',
                    'family_name' => 'Doe',
                ]],
                'price' => [
                    'currency' => 'USD',
                    'baseFare' => 180,
                    'taxes' => 40,
                    'totalFare' => 220,
                ],
            ],
        ]);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('SB-BKG-1', $booking->bookingReference);
        $this->assertSame('XYZ789', $booking->pnr);
        $this->assertCount(1, $booking->travelers);
        $this->assertSame(220.0, $booking->totalPrice?->totalAmount);
    }

    public function test_maps_sabre_soap_search_fixture_with_segment_and_pricing_fidelity(): void
    {
        $raw = $this->fixture('sabre_soap_search_decoded.json');
        $offers = (new SabreFlightOfferMapper)->mapOffers($raw);

        $this->assertCount(1, $offers);
        $this->assertSame('SOAP-TOKEN-1', $offers[0]->providerOfferReference);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
        $this->assertSame('JED', $offers[0]->segments[0]->arrivalAirport);
        $this->assertSame(252.0, $offers[0]->price?->totalAmount);
    }

    public function test_maps_sabre_soap_pricing_fixture_with_total_fare(): void
    {
        $raw = $this->fixture('sabre_soap_pricing_decoded.json');
        $price = (new SabrePriceBreakdownMapper)->mapPriceBreakdown($raw);

        $this->assertSame('confirmed', $price->status);
        $this->assertSame(264.0, $price->totalAmount);
        $this->assertSame('SOAP-PRICE-TOKEN-1', $price->offerReference);
    }

    public function test_maps_sabre_soap_booking_fixture_with_pnr_and_travelers(): void
    {
        $raw = $this->fixture('sabre_soap_booking_decoded.json');
        $booking = (new SabreBookingMapper)->mapBooking($raw);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('PNR123', $booking->pnr);
        $this->assertCount(2, $booking->travelers);
        $this->assertSame(290.0, $booking->totalPrice?->totalAmount);
    }

    public function test_maps_multi_leg_mixed_cabin_soap_fixture(): void
    {
        $raw = $this->fixture('sabre_soap_search_multileg_mixed_cabin_decoded.json');
        $offers = (new SabreFlightOfferMapper)->mapOffers($raw);

        $this->assertCount(1, $offers);
        $this->assertCount(2, $offers[0]->segments);
        $this->assertSame('KHI', $offers[0]->segments[0]->departureAirport);
        $this->assertSame('DOH', $offers[0]->segments[1]->departureAirport);
        $this->assertSame('MIXED', $offers[0]->cabinSummary);
    }

    public function test_maps_rest_grouped_itinerary_using_schedule_descriptor_ids(): void
    {
        $offers = (new SabreFlightOfferMapper)->mapOffers([
            'groupedItineraryResponse' => [
                'scheduleDescs' => [
                    [
                        'id' => 11,
                        'departure' => ['airport' => 'LHR', 'time' => '2026-06-01T09:00:00'],
                        'arrival' => ['airport' => 'JFK', 'time' => '2026-06-01T15:00:00'],
                        'carrier' => ['marketing' => 'BA', 'operating' => 'BA'],
                        'flightNumber' => 117,
                    ],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [[
                        'id' => 'itn_desc_1',
                        'pricingToken' => 'DESC-TOKEN-1',
                        'pricingInformation' => [[
                            'fare' => [
                                'currency' => 'USD',
                                'baseFare' => 100,
                                'taxes' => 20,
                                'totalFare' => 120,
                            ],
                        ]],
                        'legs' => [[
                            'segments' => [11],
                        ]],
                    ]],
                ]],
            ],
        ]);

        $this->assertCount(1, $offers);
        $this->assertSame('LHR', $offers[0]->segments[0]->departureAirport);
        $this->assertSame('JFK', $offers[0]->segments[0]->arrivalAirport);
        $this->assertSame('BA', $offers[0]->segments[0]->marketingCarrier);
    }

    public function test_payload_builder_maps_cabin_names_to_bfm_codes(): void
    {
        $payload = (new SabreFlightSearchPayloadBuilder)->forSearch(new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            cabinClass: 'economy',
        ));

        $this->assertSame('Y', data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.CabinPref.0.Cabin'));
    }

    public function test_maps_soap_retrieve_booking_fixture_with_reference_and_status_invariant(): void
    {
        $raw = $this->fixture('sabre_soap_booking_retrieve_decoded.json');
        $booking = (new SabreBookingMapper)->mapBooking($raw);

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('SB-RET-001', $booking->bookingReference);
        $this->assertSame('RETLOC1', $booking->pnr);
        $this->assertCount(1, $booking->travelers);
        $this->assertSame(225.0, $booking->totalPrice?->totalAmount);
    }

    public function test_maps_soap_cancel_booking_fixture_with_cancelled_status_invariant(): void
    {
        $raw = $this->fixture('sabre_soap_booking_cancel_decoded.json');
        $booking = (new SabreBookingMapper)->mapBooking($raw);

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('CANCEL01', $booking->bookingReference);
        $this->assertCount(1, $booking->travelers);
    }

    public function test_price_breakdown_invariant_total_not_less_than_base_plus_tax(): void
    {
        $price = (new SabrePriceBreakdownMapper)->mapPriceBreakdown([
            'price' => [
                'currency' => 'USD',
                'baseFare' => 200,
                'taxes' => 60,
                'totalFare' => 50,
            ],
        ]);

        $this->assertSame(260.0, $price->totalAmount);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $filename): array
    {
        $path = base_path('tests/Fixtures/integrations/'.$filename);
        $this->assertFileExists($path);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

