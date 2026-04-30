<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Master switch for all HTTP display-FX providers (Frankfurter, Currency API, exchangerate.host).
    | PHPUnit sets this false so tests stay deterministic; production defaults true.
     */
    'display_fx' => [
        'http_providers_enabled' => env('DISPLAY_FX_HTTP_PROVIDERS_ENABLED', true),
    ],

    /*
    | ECB-backed Frankfurter API for display-only FX when DB rates are missing.
    | Additional providers below cover currencies ECB may omit (e.g. many Asian pairs).
     */
    'frankfurter' => [
        'display_fx_enabled' => env('FRANKFURTER_DISPLAY_FX_ENABLED', true),
        'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.app'),
        'timeout_seconds' => (int) env('FRANKFURTER_TIMEOUT_SECONDS', 5),
        'cache_seconds' => (int) env('FRANKFURTER_CACHE_SECONDS', 3600),
    ],

    /*
    | Broad fiat matrix (fawazahmed0 / Cloudflare Pages) — use when Frankfurter lacks a pair.
     */
    'currency_api' => [
        'display_fx_enabled' => env('CURRENCY_API_DISPLAY_FX_ENABLED', true),
        'base_url' => env('CURRENCY_API_BASE_URL', 'https://latest.currency-api.pages.dev/v1/currencies'),
        'timeout_seconds' => (int) env('CURRENCY_API_TIMEOUT_SECONDS', 8),
        'cache_seconds' => (int) env('CURRENCY_API_CACHE_SECONDS', 3600),
    ],

    /*
    | Secondary HTTP fallback (exchangerate.host) when earlier providers fail.
     */
    'exchangerate_host' => [
        'display_fx_enabled' => env('EXCHANGERATE_HOST_DISPLAY_FX_ENABLED', true),
        'base_url' => env('EXCHANGERATE_HOST_BASE_URL', 'https://api.exchangerate.host'),
        'timeout_seconds' => (int) env('EXCHANGERATE_HOST_TIMEOUT_SECONDS', 8),
        'cache_seconds' => (int) env('EXCHANGERATE_HOST_CACHE_SECONDS', 3600),
    ],

    /*
    | Airport autocomplete fallback for broad global coverage when local slim index
    | misses a query (e.g. MEL). Local dataset remains the primary source.
     */
    'airport_directory' => [
        'remote_search_enabled' => env('AIRPORT_REMOTE_SEARCH_ENABLED', true),
        'remote_base_url' => env('AIRPORT_REMOTE_BASE_URL', 'https://raw.githubusercontent.com/mwgg/Airports/master/airports.json'),
        'remote_timeout_seconds' => (int) env('AIRPORT_REMOTE_TIMEOUT_SECONDS', 10),
        'remote_cache_seconds' => (int) env('AIRPORT_REMOTE_CACHE_SECONDS', 86400),
        'global_index_cache_seconds' => (int) env('AIRPORT_GLOBAL_INDEX_CACHE_SECONDS', 86400),
    ],

    /*
    | Public fare card display conversion (not settlement). Base rate from DB/API, then
    | optional markup % applied so estimates reflect a small conversion service margin.
     */
    'display_currency' => [
        'conversion_fee_percent' => (float) env('DISPLAY_CURRENCY_CONVERSION_FEE_PERCENT', 2.0),
    ],

];
