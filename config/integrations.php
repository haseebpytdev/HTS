<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active integration driver
    |--------------------------------------------------------------------------
    |
    | stub | travelport | sabre | amadeus_self_service | iati | duffel
    | legacy alias: amadeus
    |
    */
    'driver' => env('INTEGRATIONS_DRIVER', env('SUPPLIER_GDS_DRIVER', 'stub')),

    /*
    |--------------------------------------------------------------------------
    | Supported drivers (Phase 11.9 — per-request ?provider= override)
    |--------------------------------------------------------------------------
    */
    'supported_drivers' => ['stub', 'travelport', 'sabre', 'amadeus_self_service', 'amadeus', 'iati', 'duffel'],

    /*
    |--------------------------------------------------------------------------
    | Stub sample offer (Phase 11.11 — tests / demos only)
    |--------------------------------------------------------------------------
    */
    'stub_return_sample_offer' => (bool) env('INTEGRATIONS_STUB_SAMPLE_OFFER', false),

    /*
    |--------------------------------------------------------------------------
    | Phase 11.12 — Real GDS wiring order (implement toggles in code / .env per step)
    |--------------------------------------------------------------------------
    | Suggested sequence: Travelport search → Travelport pricing → Sabre search →
    | Sabre pricing → Amadeus search → Amadeus pricing → booking create per provider.
    */

    'credential_environment' => env('SUPPLIER_CREDENTIAL_ENV', 'production'),

    'token_refresh_buffer_seconds' => (int) env('SUPPLIER_TOKEN_REFRESH_BUFFER_SECONDS', 300),

    'http_timeout_seconds' => (int) env('SUPPLIER_HTTP_TIMEOUT_SECONDS', 30),
    'http_connect_timeout_seconds' => (int) env('SUPPLIER_HTTP_CONNECT_TIMEOUT_SECONDS', 10),
    // TEMP debugging only: set true only to isolate local certificate chain issues.
    'duffel_debug_disable_ssl_verify' => (bool) env('DUFFEL_DEBUG_DISABLE_SSL_VERIFY', false),

    // Local/debug only: GET /test-duffel — minimal Duffel HTTPS probe (never enable in production).
    'duffel_connectivity_test_enabled' => (bool) env('DUFFEL_CONNECTIVITY_TEST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Database-backed credentials & token persistence
    |--------------------------------------------------------------------------
    |
    | When enabled, an active integration_connections row (matching provider + credential
    | environment) supplies overrides. Tokens can be mirrored to integration_tokens so
    | restarts survive Laravel cache flush (still hot-cached via SupplierTokenCache).
    |
    */
    'use_database_credentials' => (bool) env('INTEGRATIONS_USE_DATABASE_CREDENTIALS', false),

    'persist_tokens_to_database' => (bool) env('INTEGRATIONS_PERSIST_TOKENS_TO_DATABASE', false),

    // When DB credentials are enabled, reject missing/unhealthy connections instead of falling back.
    'enforce_database_connection_health' => (bool) env('INTEGRATIONS_ENFORCE_DATABASE_CONNECTION_HEALTH', true),

    /*
    |--------------------------------------------------------------------------
    | Search quality controls
    |--------------------------------------------------------------------------
    */
    'search_snapshot_ttl_seconds' => (int) env('INTEGRATIONS_SEARCH_SNAPSHOT_TTL_SECONDS', 30),
    'provider_health_window_minutes' => (int) env('INTEGRATIONS_PROVIDER_HEALTH_WINDOW_MINUTES', 60),
    'provider_health_success_weight' => (float) env('INTEGRATIONS_PROVIDER_HEALTH_SUCCESS_WEIGHT', 0.7),
    'provider_health_latency_weight' => (float) env('INTEGRATIONS_PROVIDER_HEALTH_LATENCY_WEIGHT', 0.3),
    'booking_revalidation_required' => (bool) env('INTEGRATIONS_BOOKING_REVALIDATION_REQUIRED', true),
    'booking_revalidation_fresh_window_minutes' => (int) env('INTEGRATIONS_BOOKING_REVALIDATION_FRESH_WINDOW_MINUTES', 15),
    'booking_revalidation_snapshot_ttl_seconds' => (int) env('INTEGRATIONS_BOOKING_REVALIDATION_SNAPSHOT_TTL_SECONDS', 1200),
    'booking_precheck_require_price_validation' => (bool) env('INTEGRATIONS_BOOKING_PRECHECK_REQUIRE_PRICE_VALIDATION', true),
    'booking_precheck_require_seat_availability' => (bool) env('INTEGRATIONS_BOOKING_PRECHECK_REQUIRE_SEAT_AVAILABILITY', true),
    'booking_precheck_require_fare_rules_confirmed' => (bool) env('INTEGRATIONS_BOOKING_PRECHECK_REQUIRE_FARE_RULES_CONFIRMED', true),
    'api_key' => env('INTEGRATIONS_API_KEY', ''),
    'rate_limit_per_minute' => (int) env('INTEGRATIONS_RATE_LIMIT_PER_MINUTE', 60),
    'idempotency_ttl_seconds' => (int) env('INTEGRATIONS_IDEMPOTENCY_TTL_SECONDS', 600),

];
