<?php

namespace Tests\Unit\Integrations;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;
use App\Integrations\Stub\StubBookingAdapter;
use App\Integrations\Stub\StubFlightPriceAdapter;
use App\Integrations\Stub\StubFlightSearchAdapter;
use Tests\TestCase;

class FakeProviderPayloadMapperTest extends TestCase
{
    public function test_provider_specific_flight_search_payloads_map_to_normalized_offers(): void
    {
        config(['integrations.stub_scenario' => 'flight_search_success']);

        foreach (['travelport', 'sabre', 'amadeus'] as $provider) {
            $offers = (new StubFlightSearchAdapter($provider))->searchFlights(
                new \App\Data\Integrations\FlightSearchRequestData('KHI', 'JED', '2026-06-01')
            );

            $this->assertNotEmpty($offers);
            $this->assertSame($provider, $offers[0]->metadata?->provider);
            $this->assertNotEmpty($offers[0]->segments[0]->departureAirport);
        }
    }

    public function test_provider_specific_pricing_payloads_map_to_normalized_price_breakdown(): void
    {
        config(['integrations.stub_scenario' => 'flight_pricing_success']);

        foreach (['travelport', 'sabre', 'amadeus'] as $provider) {
            $price = (new StubFlightPriceAdapter($provider))->revalidateFare('REF-1');
            $this->assertGreaterThan(0, $price->totalAmount);
            $this->assertSame($provider, $price->metadata?->provider);
        }
    }

    public function test_provider_specific_booking_payloads_map_to_normalized_booking_snapshot(): void
    {
        config(['integrations.stub_scenario' => 'booking_success']);

        $request = new BookingCreateRequestData(
            offerReference: 'REF-1',
            travelers: [
                new TravelerData('adult', 'Test', 'Traveler'),
            ]
        );

        foreach (['travelport', 'sabre', 'amadeus'] as $provider) {
            $booking = (new StubBookingAdapter($provider))->createBooking($request);
            $this->assertSame('confirmed', $booking->status);
            $this->assertSame($provider, $booking->providerCode);
            $this->assertNotNull($booking->totalPrice);
        }
    }
}
