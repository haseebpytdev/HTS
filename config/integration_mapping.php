<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Normalized schema identifiers (internal contract versions)
    |--------------------------------------------------------------------------
    |
    | Bump these when breaking changes are introduced to mapper output shape.
    |
    */
    'normalized_schemas' => [
        'flight_offer' => env('INTEGRATION_SCHEMA_FLIGHT_OFFER', 'flight_offer.v1'),
        'price_breakdown' => env('INTEGRATION_SCHEMA_PRICE_BREAKDOWN', 'price_breakdown.v1'),
        'booking' => env('INTEGRATION_SCHEMA_BOOKING', 'booking.v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-provider mapper stamp (wire family + release + mapper semver)
    |--------------------------------------------------------------------------
    |
    | Used by NormalizedPayloadMetadata::forSchemaKey($provider, $schemaKey).
    |
    */
    'providers' => [
        'stub' => [
            'provider_api_family' => 'stub',
            'provider_version' => '0',
            'mapper_version' => '1.0.0',
        ],
        'travelport' => [
            'provider_api_family' => env('TRAVELPORT_API_FAMILY', 'air_v11'),
            'provider_version' => env('TRAVELPORT_PROVIDER_VERSION', '2026-01'),
            'mapper_version' => env('TRAVELPORT_MAPPER_VERSION', '1.0.0'),
        ],
        'sabre' => [
            'provider_api_family' => env('SABRE_API_FAMILY', 'rest_v1'),
            'provider_version' => env('SABRE_PROVIDER_VERSION', '2026-01'),
            'mapper_version' => env('SABRE_MAPPER_VERSION', '1.0.0'),
        ],
        'amadeus' => [
            'provider_api_family' => env('AMADEUS_API_FAMILY', 'shopping_v2'),
            'provider_version' => env('AMADEUS_PROVIDER_VERSION', '2026-01'),
            'mapper_version' => env('AMADEUS_MAPPER_VERSION', '1.0.0'),
        ],
        'amadeus_self_service' => [
            'provider_api_family' => env('AMADEUS_API_FAMILY', 'shopping_v2'),
            'provider_version' => env('AMADEUS_PROVIDER_VERSION', '2026-01'),
            'mapper_version' => env('AMADEUS_MAPPER_VERSION', '1.0.0'),
        ],
        'duffel' => [
            'provider_api_family' => env('DUFFEL_API_FAMILY', 'air_v2'),
            'provider_version' => env('DUFFEL_PROVIDER_VERSION', '2026-01'),
            'mapper_version' => env('DUFFEL_MAPPER_VERSION', '1.0.0'),
        ],
    ],
];
