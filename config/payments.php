<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default payment gateway driver
    |--------------------------------------------------------------------------
    |
    | Used by PaymentService for external captures. "manual" simulates success
    | for local/testing; "stripe" uses the Stripe REST API when configured.
    |
    */
    'driver' => env('PAYMENT_GATEWAY_DRIVER', 'manual'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
    ],

];
