<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Contracts\Integrations\FlightSearchProviderInterface;
use App\Contracts\Integrations\IdentifiesIntegrationProvider;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Data\Integrations\TravelerData;
use Tests\TestCase;

class SupplierIntegrationBindingsTest extends TestCase
{
    public function test_stub_driver_binds_all_integration_contracts(): void
    {
        config(['integrations.driver' => 'stub']);

        $search = $this->app->make(FlightSearchProviderInterface::class);
        $pricing = $this->app->make(FlightPricingProviderInterface::class);
        $booking = $this->app->make(BookingProviderInterface::class);
        $auth = $this->app->make(AuthTokenProviderInterface::class);

        $this->assertInstanceOf(IdentifiesIntegrationProvider::class, $search);
        $this->assertInstanceOf(IdentifiesIntegrationProvider::class, $auth);

        $this->assertSame('stub', $search->providerCode());
        $this->assertSame('stub', $pricing->providerCode());
        $this->assertSame('stub', $booking->providerCode());
        $this->assertSame('stub', $auth->providerCode());

        $request = new FlightSearchRequestData('KHI', 'JED', '2026-06-01', 2, 0, 0);
        $this->assertSame([], $search->searchFlights($request));

        $price = $pricing->revalidateFare('offer-1');
        $this->assertSame('unavailable', $price->status);
        $this->assertSame('offer-1', $price->offerReference);
        $this->assertNotNull($price->metadata);
        $this->assertSame('price_breakdown.v1', $price->metadata->normalizedSchemaVersion);

        $retrieved = $booking->retrieveBooking('PNR1');
        $this->assertSame('not_found', $retrieved->status);
        $this->assertSame('PNR1', $retrieved->bookingReference);
        $this->assertNotNull($retrieved->metadata);

        $create = $booking->createBooking(new BookingCreateRequestData(
            offerReference: 'offer-1',
            travelers: [new TravelerData('adult', 'A', 'B')],
        ));
        $this->assertSame('not_implemented', $create->status);
        $this->assertNotNull($create->metadata);

        $encoded = json_encode($price, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('mapper_version', $decoded);
        $this->assertArrayHasKey('normalized_schema_version', $decoded);
    }
}
