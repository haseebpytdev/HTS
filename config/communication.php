<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Communication Hub
    |--------------------------------------------------------------------------
    |
    | Centralized, scalable communication channel settings.
    | Providers are bound behind contracts to allow backend swaps later.
    |
    */
    'channels' => [
        'email' => env('COMM_EMAIL_PROVIDER', 'laravel_mail'),
        'whatsapp' => env('COMM_WHATSAPP_PROVIDER', 'open_source_webhook'),
        'sms' => env('COMM_SMS_PROVIDER', 'open_source_webhook'),
        'notification' => env('COMM_NOTIFICATION_PROVIDER', 'database'),
    ],

    'email' => [
        'default_from_name' => env('COMM_EMAIL_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Hayat Travel Solutions'))),
        'default_from_address' => env('COMM_EMAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
    ],

    'whatsapp' => [
        'webhook_base_url' => env('COMM_WHATSAPP_WEBHOOK_BASE_URL', ''),
        'token' => env('COMM_WHATSAPP_WEBHOOK_TOKEN', ''),
        'timeout_seconds' => (int) env('COMM_WHATSAPP_TIMEOUT_SECONDS', 10),
    ],

    'sms' => [
        'webhook_base_url' => env('COMM_SMS_WEBHOOK_BASE_URL', ''),
        'token' => env('COMM_SMS_WEBHOOK_TOKEN', ''),
        'timeout_seconds' => (int) env('COMM_SMS_TIMEOUT_SECONDS', 10),
    ],
];
