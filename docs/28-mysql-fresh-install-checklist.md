# MySQL Fresh Install Checklist

## Goal

Prove a brand-new MySQL database can be created and validated from repository code before staging/production rollout.

This checklist is intended for deployment readiness, not feature development.

## Preconditions

- Application code is up to date on target branch.
- `.env` is configured for the target environment.
- MySQL server is reachable from the app host.
- PHP dependencies are installed (`composer install --no-dev --optimize-autoloader` for deployment builds).

## 1) Create a clean MySQL database

Use your DBA-approved naming convention and credentials.

Example:

```sql
CREATE DATABASE apnasafar_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
```

Set environment values:

- `DB_CONNECTION=mysql`
- `DB_HOST=...`
- `DB_PORT=3306`
- `DB_DATABASE=apnasafar_fresh`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

## 2) App bootstrap and cache safety

Run:

```bash
php artisan key:generate
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Why:

- ensures runtime uses current `.env`
- prevents stale cache artifacts from masking install issues

## 3) Run migrations on clean DB

Run:

```bash
php artisan migrate --database=mysql --force
```

Expected:

- command exits successfully
- no SQL syntax/constraint errors
- all migration batches are recorded in `migrations` table

## 4) Seed required baseline data

Run:

```bash
php artisan db:seed --database=mysql --force
```

Expected:

- seeders complete without duplicate-key or missing-table failures
- admin/login-critical baseline records exist (according to current seeder set)

## 5) Storage and filesystem link

Run:

```bash
php artisan storage:link
```

Expected:

- public storage symlink exists and is readable by web user

## 6) Final optimization/cache build (deployment mode)

Run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Expected:

- all cache compile commands succeed
- no route serialization / closure route errors

## 7) Schema drift pre-check (recommended gate)

Run:

```bash
php artisan db:audit-migrations --database=mysql
```

Expected:

- no critical required-table failures
- drift output is reviewed and understood before promotion

Note:

- this command is read-only and intended to surface risk before deployment

## 8) Targeted smoke tests

Run a focused subset that proves boot + core admin modules:

```bash
php artisan test tests/Feature/Admin/SettingsModuleTest.php
php artisan test tests/Feature/Admin/CmsSeoAccessTest.php
php artisan test tests/Feature/Admin/InquiryCrmTest.php
```

Expected:

- tests pass in target environment or CI-equivalent environment
- no schema-not-found failures for core admin modules

## 9) Confirm admin app boots

Minimum checks (manual or scripted health checks):

- app homepage responds
- admin login page responds
- authenticated admin dashboard loads
- settings page and SEO/CMS page load without SQL errors

## Success criteria (release gate)

Fresh-install validation is complete only when all are true:

- clean DB migrated successfully from code
- seed completed successfully
- storage link is valid
- caches can be rebuilt cleanly
- targeted smoke tests pass
- admin pages boot without DB exceptions

## Common failure diagnosis

## Migration fails

- Verify MySQL credentials/host/port/database.
- Ensure DB user has create/alter/index/foreign key permissions.
- Check for migration history drift with:
  - `php artisan db:audit-migrations --database=mysql`

## Seeder fails

- Confirm migration step completed first.
- Check for duplicate seed data assumptions or unique constraints.
- Rerun seeder on truly clean DB to isolate idempotency issues.

## Page loads fail after deploy

- Clear and rebuild caches:
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
- Verify `.env` values used by web process (not only CLI).

## Storage/media errors

- Confirm `storage:link` target exists.
- Verify filesystem permissions for web/PHP user.

## Drift warnings appear

- Do not patch schema manually first.
- Create forward-only repair migrations for verified drift.
- Re-run migration audit and attach output to deployment notes.

## Sign-off template

Record these values for release evidence:

- environment:
- git revision:
- DB name:
- migrate result:
- seed result:
- audit result (`db:audit-migrations`):
- smoke test result:
- admin boot verification:
- approver:
