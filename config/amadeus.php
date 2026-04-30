<?php

return [
    'base_url' => env('AMADEUS_BASE_URL'),
    'token_path' => env('AMADEUS_TOKEN_PATH', '/v1/security/oauth2/token'),
    'live_enabled' => (bool) env('AMADEUS_LIVE_ENABLED', false),
    'endpoints' => [
        'flight_search' => env('AMADEUS_FLIGHT_SEARCH_ENDPOINT', '/v2/shopping/flight-offers'),
        'flight_search_max_results' => (int) env('AMADEUS_FLIGHT_SEARCH_MAX_RESULTS', 20),
        'flight_pricing' => env('AMADEUS_FLIGHT_PRICING_ENDPOINT', '/v1/shopping/flight-offers/pricing'),
        'flight_booking' => env('AMADEUS_FLIGHT_BOOKING_ENDPOINT', '/v1/booking/flight-orders'),
        'flight_booking_retrieve' => env('AMADEUS_FLIGHT_BOOKING_RETRIEVE_ENDPOINT', '/v1/booking/flight-orders/{id}'),
        'flight_booking_cancel' => env('AMADEUS_FLIGHT_BOOKING_CANCEL_ENDPOINT', '/v1/booking/flight-orders/{id}'),
        'flight_booking_ticket' => env('AMADEUS_FLIGHT_BOOKING_TICKET_ENDPOINT', '/v1/booking/flight-orders/{id}/ticket'),
        'flight_booking_amend' => env('AMADEUS_FLIGHT_BOOKING_AMEND_ENDPOINT', '/v1/booking/flight-orders/{id}/change'),
        'currency' => env('AMADEUS_DEFAULT_CURRENCY', 'USD'),
    ],
    'credentials' => [
        'test' => [
            'client_id' => env('AMADEUS_TEST_API_KEY'),
            'client_secret' => env('AMADEUS_TEST_API_SECRET'),
        ],
        'production' => [
            'client_id' => env('AMADEUS_PRODUCTION_API_KEY'),
            'client_secret' => env('AMADEUS_PRODUCTION_API_SECRET'),
        ],
    ],
];
