# Environment and deployment

Phase **16** — operational reference for running and shipping Hayat Travel Solutions Portal.

## Runtime

- **PHP:** 8.2+
- **Framework:** Laravel 11
- **Database:** MySQL 8+ (production); SQLite acceptable for local/tests (`phpunit.xml` uses in-memory SQLite).

## Environment variables (essentials)

| Area | Variables | Notes |
|------|-----------|--------|
| App | `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | Generate key with `php artisan key:generate` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Match `.env.example`; use MySQL in production |
| Session / cache | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Often `database` for small deployments |
| Files | `FILESYSTEM_DISK` | `local` or `public`; uploads use `public` disk for CMS galleries |
| Supplier GDS | `SUPPLIER_GDS_DRIVER`, `SUPPLIER_CREDENTIAL_ENV`, optional `INTEGRATIONS_*`, `TRAVELPORT_*`, `SABRE_*`, `AMADEUS_*` | See `config/integrations.php`, `config/travelport.php`, etc. |
| Payments | `PAYMENT_GATEWAY_DRIVER` (`manual` \| `stripe`), optional `STRIPE_SECRET` | See `config/payments.php`, `docs/18-payment-infrastructure.md` |

Full supplier-related keys are documented in `.env.example`.

## First-time deploy (clean server)

1. Clone the repo and `cd apnasafar-portal`.
2. `composer install --no-dev --optimize-autoloader` (use `--no-dev` only for production).
3. Copy `.env.example` → `.env`, set `APP_KEY`, database, `APP_URL`.
4. `php artisan migrate --force` (or apply migrations selectively — see below). If the repo contains **`database/schema/mysql-schema.sql`**, Laravel may load it on the first migrate into an **empty** database, then run only newer PHP migrations. Migration PHP files live in **layered folders** under `database/migrations/` (paths loaded from `AppServiceProvider`) — see **`docs/21-database-migration-architecture.md`** and **`database/migrations/README.md`**.
5. `php artisan storage:link` so `public/storage` serves user-uploaded CMS images.
6. `php artisan config:cache` / `route:cache` when appropriate.
7. Seed only if you want demo data: `php artisan db:seed` (optional in production).

**Refreshing the MySQL schema dump (maintainers):** after migrations change on MySQL, run `php artisan schema:dump --database=mysql` (`mysqldump` on `PATH`) and commit `database/schema/mysql-schema.sql` when the team agrees. See **`docs/22-schema-baseline-and-api.md`**.

## Migrations and duplicate-table risk

On databases that were created or altered **outside** Laravel’s migration history, a full `php artisan migrate` can fail (e.g. `cache` or `sessions` already exists). That does not invalidate individual migration files.

**Safe pattern:** apply only the migrations you need (paths include the layer folder):

```bash
php artisan migrate --path=database/migrations/_extensions_cms_audit/2026_04_07_120000_create_export_histories_table.php --force
php artisan migrate --path=database/migrations/_extensions_cms_audit/2026_04_08_100000_create_content_blocks_table.php --force
php artisan migrate --path=database/migrations/_extensions_integrations/2026_04_09_120000_create_integration_observability_tables.php --force
php artisan migrate --path=database/migrations/_extensions_integrations/2026_04_10_130000_create_integration_logs_table.php --force
php artisan migrate --path=database/migrations/_extensions_booking_commerce_crm/2026_04_16_100000_booking_engine_schema.php --force
php artisan migrate --path=database/migrations/_extensions_booking_commerce_crm/2026_04_17_120000_payment_infrastructure.php --force
php artisan migrate --path=database/migrations/_extensions_booking_commerce_crm/2026_04_18_100000_b2c_customer_portal.php --force
php artisan migrate --path=database/migrations/_extensions_booking_commerce_crm/2026_04_19_120000_crm_sales_pipeline.php --force
php artisan migrate --path=database/migrations/_extensions_operational/2026_12_01_000000_add_operational_indexes_for_scale.php --force
```

Long-term: align the `migrations` table with the real schema or refresh non-production databases so `migrate` can run end-to-end.

## Testing in CI

- PHPUnit uses the DB defined in `phpunit.xml` (typically in-memory SQLite); no MySQL required for `php artisan test`.

## PDF and CSV exports

- Quotation PDF uses DomPDF (`barryvdh/laravel-dompdf`); ensure PHP has adequate memory for large HTML→PDF jobs.
- CSV streams do not require extra config; **export audit** rows go to `export_histories` (migration `2026_04_07_120000_create_export_histories_table.php`).

## Related docs

- Migration layers + dependency graph: `docs/21-database-migration-architecture.md`
- Table index: `docs/15-db-schema-summary.md`
- Internal + integrations HTTP surface: `docs/05-api-contract.md`
- Hayat Travel Solutions consumer integration: `docs/16-apnasafar-integration.md`
- Payments (service layer): `docs/18-payment-infrastructure.md`
- B2C customer portal: `docs/19-b2c-customer-portal.md`
