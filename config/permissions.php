<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Role -> Permission Matrix
    |--------------------------------------------------------------------------
    |
    | This extends existing role middleware with explicit permission keys.
    | Keep keys stable; use:
    | - module.* for module-level permissions
    | - action.* for action-level permissions
    |
    */
    'role_matrix' => [
        'super_admin' => ['*'],
        'admin' => [
            'module.dashboard.view',
            'module.analytics.view',
            'module.quotations.view',
            'module.bookings.view',
            'module.inquiries.view',
            'module.support.view',
            'module.marketing.view',
            'module.compliance.view',
            'module.exports.view',

            'action.quotations.create',
            'action.quotations.update',
            'action.quotations.delete',
            'action.quotations.duplicate',
            'action.quotations.export',
            'action.quotations.convert_booking',

            'action.bookings.manage',
            'action.bookings.payments',
            'action.inquiries.manage',
            'action.support.manage',
            'action.marketing.manage',
            'action.compliance.manage',
        ],
        'sales_operator' => [
            'module.dashboard.view',
            'module.analytics.view',
            'module.quotations.view',
            'module.bookings.view',
            'module.inquiries.view',
            'module.support.view',
            'module.marketing.view',
            'module.compliance.view',

            'action.quotations.create',
            'action.quotations.update',
            'action.quotations.duplicate',
            'action.quotations.export',
            'action.quotations.convert_booking',

            'action.bookings.manage',
            'action.bookings.payments',
            'action.inquiries.manage',
            'action.support.manage',
            'action.marketing.manage',
            'action.compliance.manage',
        ],
        'agency_user' => [
            'module.agency.dashboard.view',
            'module.agency.quotations.view',
            'module.agency.inquiries.view',
            'module.agency.profile.view',

            'action.agency.quotations.revision',
            'action.agency.quotations.booking_intent',
            'action.agency.profile.update',
        ],
    ],
];
