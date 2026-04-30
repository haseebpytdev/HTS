<?php

return [
    'base_url' => env('SABRE_BASE_URL'),
    'soap_base_url' => env('SABRE_SOAP_BASE_URL'),
    'token_path' => env('SABRE_TOKEN_PATH', '/v2/auth/token'),
    'auth' => [
        // OAuth Token Create mode can run as password grant (Sabre User ID/Password)
        // or client credentials depending on account setup.
        'grant_type' => env('SABRE_AUTH_GRANT_TYPE', 'password'),
    ],
    'live_enabled' => (bool) env('SABRE_LIVE_ENABLED', false),
    'rest_search_enabled' => (bool) env('SABRE_REST_SEARCH_ENABLED', false),
    'soap_enabled' => (bool) env('SABRE_SOAP_ENABLED', false),
    'bfm' => [
        'request_type' => env('SABRE_BFM_REQUEST_TYPE', '50ITINS'),
        'requestor_id' => env('SABRE_BFM_REQUESTOR_ID', 'DEVCENTER'),
        'pseudo_city_code' => env('SABRE_BFM_PSEUDO_CITY_CODE'),
        'company_code' => env('SABRE_BFM_COMPANY_CODE', 'TN'),
    ],
    'endpoints' => [
        'flight_search' => env('SABRE_FLIGHT_SEARCH_ENDPOINT', '/v5/offers/shop'),
        'flight_search_soap' => env('SABRE_FLIGHT_SEARCH_SOAP_ENDPOINT', '/v3.3.0/OTA_AirLowFareSearchRQ'),
        'flight_search_soap_action' => env('SABRE_FLIGHT_SEARCH_SOAP_ACTION', 'OTA_AirLowFareSearchRQ'),
        'flight_pricing' => env('SABRE_FLIGHT_PRICING_ENDPOINT', '/v4/offers/price'),
        'flight_pricing_soap' => env('SABRE_FLIGHT_PRICING_SOAP_ENDPOINT', '/v3.3.0/OTA_AirPriceRQ'),
        'flight_pricing_soap_action' => env('SABRE_FLIGHT_PRICING_SOAP_ACTION', 'OTA_AirPriceRQ'),
        'flight_booking' => env('SABRE_FLIGHT_BOOKING_ENDPOINT', '/v3/offers/book'),
        'flight_booking_soap' => env('SABRE_FLIGHT_BOOKING_SOAP_ENDPOINT', '/v2.5.0/CreatePassengerNameRecordRQ'),
        'flight_booking_soap_action' => env('SABRE_FLIGHT_BOOKING_SOAP_ACTION', 'CreatePassengerNameRecordRQ'),
        'flight_booking_retrieve' => env('SABRE_FLIGHT_BOOKING_RETRIEVE_ENDPOINT', '/v3/bookings/{id}'),
        'flight_booking_retrieve_soap' => env('SABRE_FLIGHT_BOOKING_RETRIEVE_SOAP_ENDPOINT', '/v1.19.0/GetReservationRQ'),
        'flight_booking_retrieve_soap_action' => env('SABRE_FLIGHT_BOOKING_RETRIEVE_SOAP_ACTION', 'GetReservationRQ'),
        'flight_booking_cancel' => env('SABRE_FLIGHT_BOOKING_CANCEL_ENDPOINT', '/v3/bookings/{id}'),
        'flight_booking_cancel_soap' => env('SABRE_FLIGHT_BOOKING_CANCEL_SOAP_ENDPOINT', '/v1.0.0/CancelReservationRQ'),
        'flight_booking_cancel_soap_action' => env('SABRE_FLIGHT_BOOKING_CANCEL_SOAP_ACTION', 'CancelReservationRQ'),
        'flight_booking_ticket' => env('SABRE_FLIGHT_BOOKING_TICKET_ENDPOINT', '/v3/bookings/{id}/ticket'),
        'flight_booking_amend' => env('SABRE_FLIGHT_BOOKING_AMEND_ENDPOINT', '/v3/bookings/{id}/change'),
    ],
    // Runtime auth for Sabre is database-only via ProviderCredentialResolver.
    // Keep these blocks empty to avoid config/env credential fallback in runtime paths.
    'credentials' => [
        'test' => [
            'client_id' => null,
            'client_secret' => null,
        ],
        'production' => [
            'client_id' => null,
            'client_secret' => null,
        ],
    ],
];
