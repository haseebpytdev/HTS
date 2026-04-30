<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class LegacyRouteCompatibilityTest extends TestCase
{
    public function test_legacy_package_index_redirects_to_new_packages_route(): void
    {
        $response = $this->get('/raw/package/index.php?search=umrah&min_price=100000&max_price=250000');

        $response->assertRedirect('/packages?q=umrah&price_min=100000&price_max=250000');
    }

    public function test_legacy_groups_by_filter_with_all_redirects_without_status_filter(): void
    {
        $response = $this->get('/raw/groups-by-filter-new.php?groups=all&search=march');

        $response->assertRedirect('/groups?q=march');
    }

    public function test_legacy_groups_by_filter_maps_groups_to_status(): void
    {
        $response = $this->get('/raw/groups-by-filter-new.php?groups=open&date_from=2026-05-01&date_to=2026-05-30');

        $response->assertRedirect('/groups?status=open&departure_from=2026-05-01&departure_to=2026-05-30');
    }
}
