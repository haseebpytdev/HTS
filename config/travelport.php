<?php

return [
    'base_url' => env('TRAVELPORT_BASE_URL'),
    'token_path' => env('TRAVELPORT_TOKEN_PATH', '/oauth/token'),
    'live_enabled' => (bool) env('TRAVELPORT_LIVE_ENABLED', false),
    'endpoints' => [
        'flight_search' => env('TRAVELPORT_FLIGHT_SEARCH_ENDPOINT', '/11/air/search'),
        'flight_search_max_results' => (int) env('TRAVELPORT_FLIGHT_SEARCH_MAX_RESULTS', 20),
        'flight_pricing' => env('TRAVELPORT_FLIGHT_PRICING_ENDPOINT', '/11/air/price'),
        'flight_booking' => env('TRAVELPORT_FLIGHT_BOOKING_ENDPOINT', '/11/air/book'),
        'flight_booking_retrieve' => env('TRAVELPORT_FLIGHT_BOOKING_RETRIEVE_ENDPOINT', '/11/air/bookings/{id}'),
        'flight_booking_cancel' => env('TRAVELPORT_FLIGHT_BOOKING_CANCEL_ENDPOINT', '/11/air/bookings/{id}'),
        'flight_booking_ticket' => env('TRAVELPORT_FLIGHT_BOOKING_TICKET_ENDPOINT', '/11/air/bookings/{id}/ticket'),
        'flight_booking_amend' => env('TRAVELPORT_FLIGHT_BOOKING_AMEND_ENDPOINT', '/11/air/bookings/{id}/change'),
    ],
    'credentials' => [
        'test' => [
            'client_id' => env('TRAVELPORT_TEST_CLIENT_ID'),
            'client_secret' => env('TRAVELPORT_TEST_CLIENT_SECRET'),
        ],
        'production' => [
            'client_id' => env('TRAVELPORT_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('TRAVELPORT_PRODUCTION_CLIENT_SECRET'),
        ],
    ],
];
