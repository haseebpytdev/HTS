# Auth and Access Control

## Roles

- `super_admin`
- `admin`
- `sales_operator`
- `agency_user`

## Route protection

- `routes/admin.php` uses `auth` + `role:super_admin,admin,sales_operator`
- `routes/agency.php` uses `auth` + `role:agency_user`

## Database linkage

- `users.role` stores role value
- `users.agency_id` links user to `agencies.id` (nullable)

## Middleware and gates

- Middleware alias: `role` (`EnsureUserHasRole`)
- Gates:
  - `access-admin-area`
  - `access-agency-area`
