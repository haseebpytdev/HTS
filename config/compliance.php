<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Approval Gate Settings
    |--------------------------------------------------------------------------
    */
    'approval_gate_ttl_hours' => (int) env('COMPLIANCE_APPROVAL_TTL_HOURS', 72),
];
