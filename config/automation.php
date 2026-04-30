<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automation backend (replaceable)
    |--------------------------------------------------------------------------
    |
    | Keep automation provider behind contracts for future upgrades.
    | Default provider uses open-source webhook orchestration (e.g. n8n).
    |
    */
    'provider' => env('AUTOMATION_PROVIDER', 'open_source_webhook'),

    /*
    |--------------------------------------------------------------------------
    | Reminder engine settings (13.1)
    |--------------------------------------------------------------------------
    */
    'reminder_lookahead_minutes' => (int) env('AUTOMATION_REMINDER_LOOKAHEAD_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Open source webhook provider defaults
    |--------------------------------------------------------------------------
    */
    'open_source_webhook' => [
        'base_url' => env('AUTOMATION_WEBHOOK_BASE_URL', ''),
        'token' => env('AUTOMATION_WEBHOOK_TOKEN', ''),
        'timeout_seconds' => (int) env('AUTOMATION_WEBHOOK_TIMEOUT_SECONDS', 10),
    ],
];
