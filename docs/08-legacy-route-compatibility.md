# Legacy Route Compatibility Plan

## Goal

Resolve old-style public URLs into the new Laravel frontend routes without relying on the legacy backend.

## Legacy URL Mapping

- Legacy: `/raw/package/index.php`
  - New target: `/packages`
  - Handler: `App\Http\Controllers\Frontend\LegacyRouteController@packageIndex`
- Legacy: `/raw/groups-by-filter-new.php`
  - New target: `/groups`
  - Handler: `App\Http\Controllers\Frontend\LegacyRouteController@groupsByFilter`

## Query Translation Rules

### Package index mapping

- `q` <- `q | search | keyword`
- `destination` <- `destination | city`
- `category` <- `category`
- `price_min` <- `price_min | min_price`
- `price_max` <- `price_max | max_price`
- `sort` <- `sort`
- `page` <- `page`

### Groups-by-filter mapping

- `q` <- `q | search | keyword`
- `status` <- `status`, else from `groups` when `groups != all`
- `departure_from` <- `departure_from | date_from`
- `departure_to` <- `departure_to | date_to`
- `sort` <- `sort`
- `page` <- `page`

## Notes

- `groups=all` intentionally resolves to `/groups` without forcing a status filter.
- Compatibility uses route aliases and redirect handlers; no proxy PHP entry points are needed.
- Web-server level rewrites can be added later if required, but app-level routing is already functional.
