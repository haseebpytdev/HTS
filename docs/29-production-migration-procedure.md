# Production Migration Procedure

## Purpose

Provide a go-live-safe, rehearsable procedure for database migrations in production-like environments.

This runbook is intentionally operational and conservative.

## Non-negotiable rule

For Red Flag 3 and production migration safety:

- **Do not modify historical migrations unless absolutely unavoidable.**
- Prefer **forward-only repair migrations** and documentation.

## Scope

Applies to:

- staging rehearsals
- production releases
- hotfix deployments that include schema changes

Out of scope:

- CI/CD redesign
- infrastructure automation changes
- destructive DB reset workflows (`db:wipe`, `migrate:fresh`)

## Roles and approvals

Before execution, assign:

- deploy operator
- DB approver (DBA/SRE/tech lead)
- rollback authority
- observer/notetaker for release evidence

Required approvals:

- code/release approval
- backup readiness approval
- migration risk review approval

## Phase 0: Pre-deploy readiness

## 0.1 Change inventory

Collect:

- release version/commit
- new migration files in release
- whether seeders are required and why
- known drift findings from `db:audit-migrations`

## 0.2 Environment health checks

Run:

```bash
php artisan about
php artisan config:clear
php artisan cache:clear
```

Verify:

- correct environment and database target
- DB connectivity and credentials
- sufficient disk space for backup and logs

## 0.3 Drift pre-check (required)

Run:

```bash
php artisan db:audit-migrations --database=mysql
```

Decision:

- if only known/accepted baseline differences: proceed with approval
- if unknown critical drift: pause release, create/approve forward-only repair migration(s) first

## Phase 1: Backup-first procedure (required)

Take a full restore-capable backup before schema changes.

Minimum backup set:

- MySQL logical dump (or physical snapshot per platform standard)
- backup metadata: timestamp, DB host, DB name, release version, operator

Validation:

- confirm backup artifact exists
- confirm restore command/process is tested or rehearsed
- record artifact location in release notes

Do not proceed without verified backup.

## Phase 2: Maintenance mode decision

Use maintenance mode when migration risk or write contention is non-trivial.

Enable:

```bash
php artisan down
```

Optional bypass for internal health checks (if your policy permits):

```bash
php artisan down --secret="temporary-release-secret"
```

If maintenance mode is not used, ensure:

- migration lock strategy is acceptable
- user-facing write windows are controlled by product/ops plan

## Phase 3: Deploy code and dependencies

1. deploy application code
2. install dependencies (deployment profile):

```bash
composer install --no-dev --optimize-autoloader
```

3. clear stale caches before migrate:

```bash
php artisan optimize:clear
```

## Phase 4: Execute migrations (forward-only)

Run:

```bash
php artisan migrate --database=mysql --force
```

Rules:

- never edit old historical migrations during release
- never use destructive reset commands in staging/production
- if migration fails, stop and switch to failure/restore procedure

## Phase 5: Seed strategy

Seed only if release requires baseline/reference data updates.

Run when required:

```bash
php artisan db:seed --database=mysql --force
```

Seeding policy:

- seeders must be idempotent or explicitly safe for rerun
- avoid seeding demo/test-only data in production

## Phase 6: Post-migrate smoke checks

## 6.1 Framework/runtime checks

Run:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Expected:

- all commands succeed without serialization/config errors

## 6.2 DB drift re-check

Run:

```bash
php artisan db:audit-migrations --database=mysql
```

Expected:

- no new unexpected drift introduced by deployment
- required app tables present

## 6.3 Application smoke checks

Verify:

- admin login page loads
- admin dashboard loads
- settings page works
- SEO/CMS admin pages load
- core API endpoint health checks respond as expected

## 6.4 Observability checks

Confirm:

- application logs show no migration-related SQL errors
- queue/workers (if applicable) are healthy
- error rate and latency remain within normal range

## Phase 7: Exit maintenance and monitor

Bring app online:

```bash
php artisan up
```

Monitor for defined stabilization window:

- app error logs
- DB error logs
- key business flows

## Failure handling and restore plan

## A) Migration command fails before partial schema write

- keep maintenance mode on
- capture failure output
- fix via forward-only migration (if possible) and retry

## B) Migration partially applied or runtime is unstable

- keep maintenance mode on
- assess whether safe forward-only repair can recover quickly
- if risk is high or time exceeds release threshold: restore DB from backup and redeploy last known good app version

## C) Restore procedure (high level)

1. keep application offline (`php artisan down`)
2. restore DB from verified pre-deploy backup
3. deploy last known good code build
4. clear/rebuild caches
5. run smoke checks
6. bring app online (`php artisan up`)

Record incident timeline and remediation actions.

## Handling known historical migration drift

If environment has known drift:

1. document drift in release notes (expected vs risky)
2. run `db:audit-migrations` before and after deployment
3. apply only reviewed forward-only repair migrations
4. do not “fix” drift by editing old migration files already executed in real environments
5. schedule schema dump refresh and documentation updates after stabilization

## Prohibited actions during go-live

- editing historical migration files to force current release success
- manual schema patching without corresponding migration and change record
- `migrate:fresh`, `db:wipe`, or rollback-destructive workflows on staging/production

## Release evidence checklist

Capture and store:

- release version/commit
- operator and approver names
- backup artifact reference
- migration output
- seed output (if used)
- pre/post audit command output
- smoke check results
- final go/no-go decision and timestamp

## Related docs

- `docs/27-migration-audit-process.md`
- `docs/28-mysql-fresh-install-checklist.md`
- `docs/26-database-source-of-truth.md`
