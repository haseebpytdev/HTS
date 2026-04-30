# SQLite Test Harness Schema Dependency Map

## Purpose

Document how SQLite test schema is assembled and which suites depend on which tables, so missing-table failures can be diagnosed quickly.

This map is maintenance-oriented and should be updated whenever:

- new schema-dependent test suites are added
- `tests/TestCase.php` fallback tables change
- migration coverage for SQLite improves

## Schema sources in tests

SQLite test runs currently use a hybrid model:

1. **Migrations path registered in app boot**
   - `database/migrations/_extensions_*`
   - `database/migrations/_testing`
2. **Fallback bootstrap in `tests/TestCase.php`**
   - `ensureDeterministicSqliteTestSchema()`
   - creates tables when absent to keep tests deterministic while baseline migrations are incomplete
3. **MySQL schema dump**
   - not directly used by SQLite test DB
   - reference only (authority for production baseline shape)

## Key suite-to-table map

## Admin CRM / CMS / Settings

Representative suites:

- `tests/Feature/Admin/InquiryCrmTest.php`
- `tests/Feature/Admin/CmsSeoAccessTest.php`
- `tests/Feature/Admin/SettingsModuleTest.php`
- `tests/Feature/Admin/AdminPermissionMatrixTest.php`
- `tests/Feature/Admin/ExportHistoryRecordingTest.php`

Primary tables:

- `inquiries` -> fallback bootstrap (`tests/TestCase.php`)
- `inquiry_activities` -> fallback bootstrap
- `inquiry_follow_ups` -> fallback bootstrap
- `seo_pages` -> fallback bootstrap
- `content_blocks` -> fallback bootstrap
- `settings` -> fallback bootstrap
- `application_settings` -> fallback bootstrap (compat for existing code paths)
- `quotations` -> fallback bootstrap
- `quotation_items` -> fallback bootstrap
- `export_histories` -> fallback bootstrap

## API v1 surface

Representative suite:

- `tests/Feature/Api/V1EndpointsTest.php`

Primary tables:

- `packages` -> fallback bootstrap
- `groups` -> fallback bootstrap
- `hotels` -> fallback bootstrap
- `inquiries` -> fallback bootstrap
- `quotations` -> fallback bootstrap
- `quotation_items` -> fallback bootstrap
- `agencies`, `destinations`, `categories` -> fallback bootstrap

## Integration observability / orchestration

Representative suites:

- `tests/Feature/Integration/IntegrationObservabilityTest.php`
- `tests/Feature/Integration/SupplierOrchestrationQualityTest.php`
- `tests/Feature/Integration/BookingRevalidationGuardApiTest.php`

Primary tables:

- `integration_connections` -> fallback bootstrap
- `integration_request_logs` -> fallback bootstrap
- `integration_response_logs` -> fallback bootstrap
- `integration_events` -> fallback bootstrap
- `integration_logs` -> migration/fallback mix (available through current migration/fallback path)

## Source-of-table quick reference

## Fallback bootstrap (`tests/TestCase.php`)

Currently includes (non-exhaustive high-impact set):

- catalog/public tables: `destinations`, `categories`, `packages`, `groups`, media/departures
- CRM/admin tables: `inquiries`, `inquiry_activities`, `inquiry_follow_ups`
- CMS/settings tables: `seo_pages`, `content_blocks`, `settings`, `application_settings`
- quotation/export tables: `quotations`, `quotation_items`, `export_histories`
- integration observability tables: `integration_connections`, `integration_request_logs`, `integration_response_logs`, `integration_events`
- supporting masters: `agencies`, `hotels`

## Migration-provided in SQLite test runs

- migration files under `database/migrations/_extensions_*` and `_testing` run as available
- coverage is partial because historical baseline migration files are not fully present on disk

## MySQL schema dump

- `database/schema/mysql-schema.sql` is not the active schema source for SQLite in-memory tests
- use for reference and MySQL cold-start optimization only

## Failure triage playbook (missing table)

When a test fails with `no such table`:

1. identify suite and missing table
2. confirm whether table should come from migration or fallback bootstrap
3. prefer migration-path fix if sqlite-compatible and practical
4. if not practical, add minimal fallback table in `tests/TestCase.php`
5. rerun only affected suite first, then affected group

## Guardrails

- keep fallback tables minimal and deterministic
- avoid duplicating the full production schema in test bootstrap
- do not change production business logic to mask schema setup gaps
- for Red Flag 3: do not modify historical migrations unless absolutely unavoidable; prefer forward-only repairs and docs

## Related docs

- `docs/26-database-source-of-truth.md`
- `docs/27-migration-audit-process.md`
- `docs/29-production-migration-procedure.md`
