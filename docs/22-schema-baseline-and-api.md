# Schema baseline, indexes, and API-ready identifiers

## Goals

- **Fast cold starts:** optional MySQL schema dump alongside incremental migrations.
- **10K+ users scale:** composite indexes aligned to real list/filter/sort queries (`agency_id`, `user_id`, `status`, `created_at`, booking/quotation FKs).
- **No duplicate business keys:** `quote_number`, `booking_number`, `agencies.code`, `customers.email` remain unique at the DB layer where defined.
- **Normalized core:** quotations vs bookings vs travelers (manifest) vs contact snapshot — see `docs/21-database-migration-architecture.md`.
- **Mobile / public API:** stable **`uuid`** on `bookings`, `quotations`, `inquiries`, `customers` (bigint `id` stays the internal PK for joins).

## Public UUID (`uuid`)

- Column: `CHAR(36)`, **unique**, **NOT NULL** on MySQL after backfill; SQLite tests rely on the same migration backfill + model hook.
- Populated automatically via **`App\Models\Concerns\HasPublicUuid`** on `creating` if empty.
- **Do not** add `uuid` to `$fillable` on these models (avoid client-supplied UUIDs).
- Query helper: `Model::whereUuid($uuid)`.
- **API v1:** `InquiryResource` and `QuotationResource` expose `uuid` next to `id`. Prefer `uuid` for mobile sync and deep links; keep `id` for backward compatibility until clients migrate.

## Multi-tenant / future org layer

Current B2B scope uses **`agency_id`** as the tenancy discriminator for agency users and data. A **`tenants`** table plus nullable **`tenant_id`** on `agencies`, `users`, and `customers` provides a forward-compatible org boundary: existing rows are backfilled to the **default** tenant (slug from `config('tenancy.default_slug')`), and models assign the default when `tenant_id` is omitted. No global query scopes were added; scope queries explicitly when you introduce true multi-tenant isolation.

A future **organization** layer can introduce e.g. `organization_id` nullable on `agencies` or a join table without renaming `uuid`; external references should use **`uuid`**, not sequential ids.

## Schema dump (lock baseline)

On a machine with **MySQL** tools available and `.env` pointing at a DB that has run **all** migrations:

```bash
php artisan migrate --force
php artisan schema:dump --database=mysql
```

Output: **`database/schema/mysql-schema.sql`**.

On the **first** `migrate` into an empty database, Laravel loads this file when present (see `MigrateCommand::loadSchemaState`), then runs only migrations **after** the dumped batch. **Do not** use `schema:dump --prune` in this repo unless you deliberately replace PHP migrations with the dump-only workflow.

**CI / PHPUnit** use SQLite in memory; they continue to run PHP migrations end-to-end (no MySQL dump required).

## Incremental migrations

Layered paths are documented in **`database/migrations/README.md`** and `docs/21-database-migration-architecture.md`.

Operational indexes:

- **`2026_12_01_000000_add_operational_indexes_for_scale`** — bookings, inquiries, quotations, payments (agency/status/created_at style).
- **`2026_12_01_100000_schema_baseline_uuids_and_indexes`** — public UUIDs + additional composites (users, bookings, quotations, inquiries, booking_items, travelers, payments, booking_intents, quotation_revision_requests, quotation_items).

## Foreign keys (core)

- `quotations`: `agency_id`, `user_id`, `inquiry_id` (FK added after `inquiries` exists).
- `bookings`: `quotation_id`, `agency_id`, `user_id`, `customer_id` (B2C).
- **Caution:** `bookings.quotation_id` uses `cascadeOnDelete()` — hard-deleting a quotation removes bookings; production should avoid hard deletes or switch to soft deletes in a future migration.

## Related

- `docs/21-database-migration-architecture.md`
- `docs/15-db-schema-summary.md`
- `docs/05-api-contract.md`
