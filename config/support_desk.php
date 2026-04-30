<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA policy (hours)
    |--------------------------------------------------------------------------
    */
    'sla_hours' => [
        'low' => [
            'first_response' => (int) env('SUPPORT_SLA_LOW_FIRST_RESPONSE_HOURS', 24),
            'resolution' => (int) env('SUPPORT_SLA_LOW_RESOLUTION_HOURS', 72),
        ],
        'medium' => [
            'first_response' => (int) env('SUPPORT_SLA_MEDIUM_FIRST_RESPONSE_HOURS', 8),
            'resolution' => (int) env('SUPPORT_SLA_MEDIUM_RESOLUTION_HOURS', 24),
        ],
        'high' => [
            'first_response' => (int) env('SUPPORT_SLA_HIGH_FIRST_RESPONSE_HOURS', 2),
            'resolution' => (int) env('SUPPORT_SLA_HIGH_RESOLUTION_HOURS', 8),
        ],
        'urgent' => [
            'first_response' => (int) env('SUPPORT_SLA_URGENT_FIRST_RESPONSE_HOURS', 1),
            'resolution' => (int) env('SUPPORT_SLA_URGENT_RESOLUTION_HOURS', 4),
        ],
    ],
];
