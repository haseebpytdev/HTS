# Migration Audit and Repair Process

## Goal

Provide one safe, repeatable process to detect and repair schema drift before deployment.

This process is **read-first** and **forward-only**:

- detect drift with one command
- review findings
- add guarded repair migrations when needed
- never rewrite old migrations
- never auto-fix inside the audit command

## Command

Run:

```bash
php artisan db:audit-migrations --database=mysql
```

Optional schema dump path override:

```bash
php artisan db:audit-migrations --database=mysql --schema=database/schema/mysql-schema.sql
```

## What the command compares

The command checks four sources:

1. migration files on disk (`database/migrations/**`)
2. local DB `migrations` table history
3. live DB table listing
4. MySQL schema dump (`database/schema/mysql-schema.sql`)

It reports:

- migration names recorded in DB but missing on disk
- migration files present on disk but not recorded in DB
- tables present in dump but missing in DB
- tables present in DB but not in dump
- schema dump migration insert mismatches
- required application tables missing in DB (`inquiries`, `seo_pages`, `application_settings`)

The command is read-only and returns non-zero when drift signals exist.

## Interpreting results safely

## Expected (non-blocking) findings

- Older migration basenames missing on disk while represented in schema dump baseline.
- New extension tables existing in DB but absent from an older schema dump.

These indicate baseline lifecycle work, not necessarily broken runtime schema.

## Action-required findings

- required application tables missing
- DB missing tables that application code actively depends on
- migration history mismatches that block deterministic bootstrapping

## Verified local repair in this phase

- Added forward-only guarded migration:
  - `database/migrations/_extensions_operational/2026_04_22_000000_create_application_settings_table.php`
- Purpose:
  - creates `application_settings` if missing
  - preserves existing data/state
  - does not modify historical migrations

## Repair policy (RF3.5)

When drift is verified:

1. Create a new migration with guarded checks (`Schema::hasTable`, `Schema::hasColumn`).
2. Keep repair additive and forward-only.
3. Avoid destructive changes unless explicitly approved and documented.
4. Run in order: local -> staging -> production with backups and rollout notes.

## Runbook

## Local

1. `php artisan db:audit-migrations --database=mysql`
2. review findings
3. apply new repair migrations:
   - `php artisan migrate --database=mysql`
4. rerun audit command to confirm drift reduced

## Staging

1. backup
2. run audit command
3. apply migrations with `--force`
4. rerun audit and smoke test critical flows

## Production

1. backup and maintenance window per environment policy
2. run audit command in read-only check mode
3. apply reviewed forward-only migrations with `--force`
4. rerun audit and capture results in deployment log

## What not to do

- do not edit old historical migration files already deployed
- do not use `db:wipe`/`migrate:fresh` for repair in staging/production
- do not manually patch schema without codifying the change in migrations
