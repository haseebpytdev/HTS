<?php

return [
    'base_url' => env('DUFFEL_BASE_URL', 'https://api.duffel.com'),
    'token_path' => env('DUFFEL_TOKEN_PATH', ''),
    'api_token' => env('DUFFEL_API_TOKEN'),
    'version' => env('DUFFEL_VERSION', 'v2'),
    'timeout_seconds' => (int) env('DUFFEL_TIMEOUT_SECONDS', env('SUPPLIER_HTTP_TIMEOUT_SECONDS', 30)),
    'health_check_path' => env('DUFFEL_HEALTH_CHECK_PATH', '/air/airlines?limit=1'),
    'live_enabled' => (bool) env('DUFFEL_LIVE_ENABLED', true),

    'environments' => [
        'test' => [
            'base_url' => env('DUFFEL_TEST_BASE_URL', env('DUFFEL_BASE_URL', 'https://api.duffel.com')),
            'api_token' => env('DUFFEL_TEST_API_TOKEN', env('DUFFEL_API_TOKEN')),
        ],
        'production' => [
            'base_url' => env('DUFFEL_PRODUCTION_BASE_URL', env('DUFFEL_BASE_URL', 'https://api.duffel.com')),
            'api_token' => env('DUFFEL_PRODUCTION_API_TOKEN', env('DUFFEL_API_TOKEN')),
        ],
    ],

    'credentials' => [
        'test' => [
            'client_id' => env('DUFFEL_TEST_CLIENT_ID', 'duffel'),
            'api_token' => env('DUFFEL_TEST_API_TOKEN', env('DUFFEL_API_TOKEN')),
            // Backward compatible mapping for older resolver paths expecting client_secret/api_key.
            'client_secret' => env('DUFFEL_TEST_CLIENT_SECRET', env('DUFFEL_TEST_API_TOKEN', env('DUFFEL_API_TOKEN'))),
            'api_key' => env('DUFFEL_TEST_API_KEY', env('DUFFEL_API_TOKEN')),
        ],
        'production' => [
            'client_id' => env('DUFFEL_PRODUCTION_CLIENT_ID', 'duffel'),
            'api_token' => env('DUFFEL_PRODUCTION_API_TOKEN', env('DUFFEL_API_TOKEN')),
            'client_secret' => env('DUFFEL_PRODUCTION_CLIENT_SECRET', env('DUFFEL_PRODUCTION_API_TOKEN', env('DUFFEL_API_TOKEN'))),
            'api_key' => env('DUFFEL_PRODUCTION_API_KEY', env('DUFFEL_API_TOKEN')),
        ],
    ],

    // Canonical Duffel API resource paths.
    'offer_requests_path' => env('DUFFEL_OFFER_REQUESTS_PATH', env('DUFFEL_FLIGHT_SEARCH_PATH', '/air/offer_requests')),
    'offers_path' => env('DUFFEL_OFFERS_PATH', env('DUFFEL_FLIGHT_PRICE_PATH', '/air/offers')),
    'orders_path' => env('DUFFEL_ORDERS_PATH', env('DUFFEL_BOOKING_PATH', '/air/orders')),
    'search' => [
        'max_results' => (int) env('DUFFEL_SEARCH_MAX_RESULTS', 25),
        'max_connections' => (int) env('DUFFEL_SEARCH_MAX_CONNECTIONS', 2),
        'cabin_class' => env('DUFFEL_SEARCH_CABIN_CLASS', ''),
    ],

    // Backward-compatible aliases used in earlier adapter scaffolding.
    'flight_search_path' => env('DUFFEL_FLIGHT_SEARCH_PATH', '/air/offer_requests'),
    'flight_price_path' => env('DUFFEL_FLIGHT_PRICE_PATH', '/air/offers'),
    'booking_path' => env('DUFFEL_BOOKING_PATH', '/air/orders'),
];
