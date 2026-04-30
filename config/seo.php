<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route name → seo_pages.page_key
    |--------------------------------------------------------------------------
    |
    | Used by ShareFrontendSeo middleware to attach metadata to public pages.
    | Detail routes (package/group show) may override in the controller.
    |
    */
    'route_page_keys' => [
        'frontend.home' => 'home',
        'frontend.about' => 'about',
        'frontend.packages.index' => 'packages_index',
        'frontend.groups.index' => 'groups_index',
        'frontend.inquiries.quote' => 'quote_inquiry',
    ],

    /*
    | Fallback page_key for dynamic detail pages when no per-record SEO exists.
    */
    'package_detail_page_key' => 'package_detail',
    'group_detail_page_key' => 'group_detail',
];
