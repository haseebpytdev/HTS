<?php

namespace Tests\Unit\Integration;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;
use App\Integrations\Sabre\Payloads\SabreBookingSoapPayloadBuilder;
use App\Integrations\Sabre\Payloads\SabreFlightPricingSoapPayloadBuilder;
use Tests\TestCase;

class SabreSoapPayloadBuildersTest extends TestCase
{
    public function test_builds_pricing_soap_envelope_with_offer_reference(): void
    {
        $xml = (new SabreFlightPricingSoapPayloadBuilder)->buildEnvelope('TOKEN-ABC', ['currency' => 'usd']);

        $this->assertStringContainsString('OTA_AirPriceRQ', $xml);
        $this->assertStringContainsString('PricingToken>TOKEN-ABC</', $xml);
        $this->assertStringContainsString('CurrencyCode="USD"', $xml);
    }

    public function test_builds_booking_create_soap_envelope_with_travelers(): void
    {
        $request = new BookingCreateRequestData(
            offerReference: 'TOKEN-BOOK',
            travelers: [
                new TravelerData('adult', 'John', 'Doe'),
                new TravelerData('adult', 'Jane', 'Doe'),
            ],
            contactEmail: 'demo@example.com',
            contactPhone: '123',
        );

        $builder = new SabreBookingSoapPayloadBuilder;
        $xml = $builder->buildCreateBookingEnvelope($request);

        $this->assertStringContainsString('CreatePassengerNameRecordRQ', $xml);
        $this->assertStringContainsString('OfferReference>TOKEN-BOOK</', $xml);
        $this->assertStringContainsString('GivenName>John</', $xml);
        $this->assertStringContainsString('GivenName>Jane</', $xml);
    }
}

