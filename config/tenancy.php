<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tenant (slug)
    |--------------------------------------------------------------------------
    |
    | Single-tenant and legacy installs use this row. Existing data is backfilled
    | to this tenant; new rows get tenant_id via model hooks when omitted.
    |
    */
    'default_slug' => env('TENANCY_DEFAULT_SLUG', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Opt-in query scoping (phase 2)
    |--------------------------------------------------------------------------
    |
    | When false (default), no global tenant scopes are applied — same as legacy.
    | Super admins can persist an override in application_settings (see TenancySettings).
    | Strict isolation as the only mode is not enabled here; flip env/DB when ready.
    |
    */
    'tenant_scoping_enabled' => (bool) env('TENANCY_SCOPING_ENABLED', false),

];
