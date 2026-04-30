<?php

return [
    'base_url' => env('IATI_BASE_URL'),
    'token_path' => env('IATI_TOKEN_PATH', '/oauth/token'),
    'credentials' => [
        'test' => [
            'client_id' => env('IATI_TEST_CLIENT_ID'),
            'client_secret' => env('IATI_TEST_CLIENT_SECRET'),
            'api_key' => env('IATI_TEST_API_KEY'),
        ],
        'production' => [
            'client_id' => env('IATI_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('IATI_PRODUCTION_CLIENT_SECRET'),
            'api_key' => env('IATI_PRODUCTION_API_KEY'),
        ],
    ],
    // Placeholder paths; confirm with official provider docs before enabling live calls.
    'flight_search_path' => env('IATI_FLIGHT_SEARCH_PATH', '/v1/flights/search'),
    'flight_price_path' => env('IATI_FLIGHT_PRICE_PATH', '/v1/flights/price'),
    'booking_path' => env('IATI_BOOKING_PATH', '/v1/bookings'),
];
