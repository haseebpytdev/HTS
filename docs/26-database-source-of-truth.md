# Database Source of Truth

## Purpose

This document defines database authority for development, testing, and deployment.

**Authority model (required):**

1. Laravel migration files are the source of truth.
2. MySQL schema dump is an optimization layer only.
3. SQLite bootstrap helpers are test-only compatibility helpers, not schema authority.
4. Manual database state is never authority.

## Canonical rule

- The canonical database definition is the migration history in `database/migrations/**`.
- Any schema visible in MySQL, SQLite tests, or deployment must be explainable by migrations.
- If runtime schema differs from migrations, treat it as drift and repair with forward-only migrations.

## Environment behavior

## Local development (MySQL)

- **Primary schema builder:** `php artisan migrate` using migration files.
- **Allowed optimization:** Laravel may load `database/schema/mysql-schema.sql` first on an empty DB, then run later migrations.
- **Non-negotiable:** schema dump cannot replace migration history as authority.
- **Expected workflow:** after structural changes are finalized and verified, regenerate schema dump to keep cold-start fast.

## Test environment (SQLite, in-memory)

- **Primary schema builder:** test-time migrations (`RefreshDatabase` flow).
- **Allowed helper:** deterministic SQLite bootstrap helper in `tests/TestCase.php` is temporary safety net for known gaps.
- **Hard limit:** bootstrap helpers must not define long-term canonical schema and must not diverge from migration intent.
- **Target state before go-live:** tests should pass from migrations alone (or explicitly documented unavoidable exceptions).

## Deployment environment (MySQL)

- **Primary schema builder:** migrations executed in deployment pipeline (`php artisan migrate --force`).
- **Schema dump usage:** allowed only as startup acceleration for empty databases; never as a substitute for running required migrations.
- **Rollback/repair policy:** use forward-only corrective migrations; do not patch live schema manually without corresponding migration.

## Schema dump policy (`database/schema/mysql-schema.sql`)

## Allowed

- Speeding up provisioning of a fresh empty MySQL database.
- Capturing current post-migration baseline for faster bootstrap.
- Being regenerated after migration changes are stable and merged.

## Not allowed

- Using dump-only changes without corresponding migration files.
- Treating dump content as an alternative authority to migrations.
- Depending on dump to hide missing migration files.
- Using `schema:dump --prune` unless a deliberate, documented migration-pruning strategy is approved.

## SQLite bootstrap helper policy

## Allowed

- Short-term stabilization for CI/tests when SQLite engine behavior differs or legacy drift exists.
- Creating minimal compatibility tables only when explicitly documented and bounded.

## Not allowed

- Silent schema ownership transfer from migrations to test bootstrap code.
- Introducing fields, table names, or constraints in bootstrap that conflict with migration-defined schema.
- Expanding helper scope as a permanent replacement for migration repair.

## Manual database change policy

## Allowed (exception path only)

- Emergency production hotfix by DBA/SRE where immediate manual SQL is unavoidable.
- Must be followed by a forward migration that codifies the exact change.
- Must be documented with timestamp, actor, SQL executed, and rollback/risk notes.

## Not allowed

- Ad-hoc local/prod schema edits that are never codified in migrations.
- Editing `migrations` table to "pretend" migrations ran without documented repair process.

## Pre-go-live database quality gate

Before go-live, all must be true:

- Fresh local MySQL builds successfully from migration workflow.
- SQLite tests create required tables deterministically and align with migration intent.
- Drift between migration files, `migrations` table, and real schema is identified and documented.
- Any drift fixes are implemented as forward-only migrations.
- Deployment migration steps are reproducible and scripted.

## Drift handling protocol

When drift is found:

1. Record mismatch between migration files, MySQL schema, and `migrations` records.
2. Decide corrective path with forward-only migration(s).
3. Apply migration in development, then test, then staging.
4. Regenerate schema dump (if used) after corrections are stable.
5. Keep this document and migration architecture docs updated.

## Related docs

- `docs/21-database-migration-architecture.md`
- `docs/22-schema-baseline-and-api.md`
- `docs/14-environment-and-deployment.md`
