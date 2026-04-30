# Database schema summary

High-level map of **application tables** (Laravel default tables such as `users`, `cache`, `jobs`, `sessions` are omitted unless noted). For integration logging detail see `docs/11-integration-observability.md`.

## Identity and access

| Table | Purpose |
|-------|---------|
| `users` | Staff and agency users; role + optional `agency_id` |
| `customers` | B2C portal accounts (separate guard); **`uuid`** (public API) |
| `customer_password_reset_tokens` | Password reset broker `customers` |
| `customer_saved_travelers` | Saved passenger profiles per customer |
| `agencies` | B2B agency master |

## B2B master data and quotations

| Table | Purpose |
|-------|---------|
| `hotels`, `hotel_room_types`, `hotel_rates` | Property and nightly rates |
| `visa_types`, `visa_rates` | Visa products and pricing |
| `transport_types`, `transport_rates` | Ground transport |
| `flight_entries` | Catalog flight rows used in Umrah quotes |
| `quotations`, `quotation_items` | Saved quotes and line items; **`uuid`** on quotations (public API) |
| `bookings` | Bookings linked to quotations; optional `customer_id` for B2C portal; lifecycle + invoice + supplier hook columns; **`uuid`** (public API) |
| `booking_items` | Line items copied from quotation at conversion |
| `travelers` | Passengers on a booking |
| `booking_status_histories` | Audit trail (status changes, hold, confirm, cancel, amend, invoice) |
| `agency_wallets` | Agency prepaid/credit balance, credit limit, overdue tracking |
| `payments`, `transactions`, `refunds` | Payment records, gateway attempts, refunds |
| `ledger_entries` | Append-only wallet movements |
| `quotation_revision_requests`, `booking_intents` | Agency ↔ staff workflow (see agency interaction migration) |

## Frontend / catalog

| Table | Purpose |
|-------|---------|
| `destinations`, `categories` | Package taxonomy |
| `packages`, `package_departures`, `package_images` | Public packages and media |
| `groups`, `group_images` | Group travel and media |
| `inquiries` | Leads (quote / package / group sources); CRM: `pipeline_stage`, `estimated_value`, `last_contacted_at`; **`uuid`** (public API) |
| `inquiry_activities` | Timeline: calls, notes, status/pipeline changes, follow-up completions |
| `inquiry_follow_ups` | Scheduled tasks / reminders (`due_at`, `assigned_to`, `completed_at`, `reminder_sent_at`) |
| `seo_pages` | Per-route SEO metadata |
| `content_blocks` | Homepage / CMS-lite structured blocks |

## Operations and audit

| Table | Purpose |
|-------|---------|
| `export_histories` | Who exported PDF/CSV and when |

## Supplier integration (GDS)

| Table | Purpose |
|-------|---------|
| `integration_connections` | Named provider connection + environment |
| `integration_credentials` | Encrypted secrets per connection |
| `integration_tokens` | Cached OAuth tokens (optional persistence) |
| `integration_request_logs`, `integration_response_logs` | Raw HTTP archive |
| `integration_events` | Optional discrete events |
| `integration_logs` | Orchestration audit |
| `supplier_search_sessions`, `supplier_offer_snapshots`, `supplier_booking_snapshots` | Normalized business snapshots |

## Migration files (reference)

Files are grouped under `database/migrations/<layer>/` (see `database/migrations/README.md`, `docs/21-database-migration-architecture.md`). Laravel runs by **filename** order.

| Layer | File | Main content |
|-------|------|----------------|
| `_foundation` | `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `_foundation` | `0001_01_01_000001_create_cache_table.php` | `cache` |
| `_foundation` | `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |
| `_core` | `2026_04_06_191500_create_agencies_table.php` | Agencies |
| `_core` | `2026_04_06_191600_add_role_and_agency_to_users_table.php` | User roles + `agency_id` |
| `_core` | `2026_04_06_200000_create_b2b_core_tables.php` | Hotels through base `bookings`, `quotations`, `settings` |
| `_catalog` | `2026_04_06_200100_create_frontend_public_tables.php` | Destinations, packages, groups, `inquiries`, SEO; `quotations.inquiry_id` FK |
| `_transactional_workflow` | `2026_04_06_223000_create_agency_quote_interaction_tables.php` | Revision requests, booking intents |
| `_transactional_workflow` | `2026_04_06_235500_add_admin_notes_to_inquiries_table.php` | Inquiry admin notes |
| `_extensions_cms_audit` | `2026_04_07_120000_create_export_histories_table.php` | Export audit |
| `_extensions_cms_audit` | `2026_04_08_100000_create_content_blocks_table.php` | CMS blocks |
| `_extensions_integrations` | `2026_04_09_120000_create_integration_observability_tables.php` | GDS observability + snapshots |
| `_extensions_integrations` | `2026_04_10_130000_create_integration_logs_table.php` | `integration_logs` |
| `_extensions_booking_commerce_crm` | `2026_04_16_100000_booking_engine_schema.php` | Booking columns + `booking_items`, `travelers`, `booking_status_histories` |
| `_extensions_booking_commerce_crm` | `2026_04_17_120000_payment_infrastructure.php` | Payments, gateway `transactions`, refunds, wallets, ledger |
| `_extensions_booking_commerce_crm` | `2026_04_18_100000_b2c_customer_portal.php` | `customers`, saved travelers, `bookings.customer_id` |
| `_extensions_booking_commerce_crm` | `2026_04_19_120000_crm_sales_pipeline.php` | Inquiry CRM + `inquiry_activities` + `inquiry_follow_ups` |
| `_extensions_operational` | `2026_12_01_000000_add_operational_indexes_for_scale.php` | Composite indexes for list/report queries |
| `_extensions_operational` | `2026_12_01_100000_schema_baseline_uuids_and_indexes.php` | Public **`uuid`** on bookings, quotations, inquiries, customers + extended indexes |
| (generated) | `database/schema/mysql-schema.sql` | MySQL baseline snapshot — run `php artisan schema:dump --database=mysql` after migrations |

## Eloquent models

Prefer `app/Models/*` as the source of truth for relationships and fillable fields; this document is an **index**, not a column-level spec.
