<?php

namespace Tests\Unit\Integration;

use App\Data\Integrations\FlightOfferData;
use App\Data\Integrations\FlightSegmentData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use Tests\TestCase;

class NormalizedPayloadMetadataTest extends TestCase
{
    public function test_for_schema_key_stamps_travelport_example_shape(): void
    {
        config([
            'integration_mapping.providers.travelport' => [
                'provider_api_family' => 'air_v11',
                'provider_version' => '2026-01',
                'mapper_version' => '1.0.0',
            ],
            'integration_mapping.normalized_schemas.flight_offer' => 'flight_offer.v1',
        ]);

        $meta = NormalizedPayloadMetadata::forSchemaKey('travelport', 'flight_offer');

        $this->assertSame('travelport', $meta->provider);
        $this->assertSame('air_v11', $meta->providerApiFamily);
        $this->assertSame('2026-01', $meta->providerVersion);
        $this->assertSame('1.0.0', $meta->mapperVersion);
        $this->assertSame('flight_offer.v1', $meta->normalizedSchemaVersion);

        $this->assertSame([
            'provider' => 'travelport',
            'provider_api_family' => 'air_v11',
            'provider_version' => '2026-01',
            'mapper_version' => '1.0.0',
            'normalized_schema_version' => 'flight_offer.v1',
        ], $meta->jsonSerialize());
    }

    public function test_flight_offer_json_includes_stamp_before_business_fields(): void
    {
        $offer = new FlightOfferData(
            id: 'o1',
            providerOfferReference: 'pref-1',
            segments: [
                new FlightSegmentData('KHI', 'JED', '2026-06-01T08:00:00Z', '2026-06-01T12:00:00Z'),
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'flight_offer'),
        );

        $json = json_decode(json_encode($offer, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('stub', $json['provider']);
        $this->assertSame('flight_offer.v1', $json['normalized_schema_version']);
        $this->assertSame('o1', $json['id']);
        $this->assertArrayHasKey('segments', $json);
    }
}
