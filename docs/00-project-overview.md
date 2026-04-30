# Project Overview

This project is a Laravel 11 codebase structured by modules and clean layers.

## Modules

- Frontend
- Auth
- Admin
- Agency
- **Integration platform (Phase 11)**
  - **Internal API:** `/api/v1` — stable JSON for first-party apps and future Hayat Travel Solutions sync (`docs/05-api-contract.md`)
  - **Supplier (GDS) layer:** `App\Contracts\Integrations\*` + adapters under `App\Integrations\{Travelport|Sabre|Amadeus}` (`docs/10-supplier-integration-layer.md`); raw + normalized persistence (`docs/11-integration-observability.md`); **JSON handling contract** (`docs/12-json-handling-rules.md`)
  - **Regression tests:** Phase 15 coverage index (`docs/13-testing-stabilization.md`) — run `php artisan test`
- **Maintainability (Phase 16)**
  - **Environment & deployment:** `docs/14-environment-and-deployment.md`
  - **DB schema index:** `docs/15-db-schema-summary.md`
  - **Migration layers / dependency order:** `docs/21-database-migration-architecture.md`, `database/migrations/README.md`
  - **Schema baseline & API UUIDs:** `docs/22-schema-baseline-and-api.md`, committed `database/schema/mysql-schema.sql` (optional fast MySQL load)
  - **Hayat Travel Solutions / external consumers:** `docs/16-apnasafar-integration.md`
  - **Refactors:** quotation form data → `QuotationBuilderFormDataService`; calculator input wiring → `UmrahQuotationInputFactory`; CSV rows → `AdminTabularCsvWriter`; SEO attributes → request trait `BuildsSeoPageAttributes`; SEO list query → `SeoPageRepository::paginateForAdmin`; quotation delete → `DeleteQuotationAction`
- **Booking engine (Phase 4):** quotation → booking, hold/confirm/cancel/amend, voucher + invoice, status history, supplier hook placeholders (`docs/17-booking-engine.md`)
- **Payment infrastructure:** wallets, ledger, payments/refunds, gateway abstraction — **`PaymentService`** / **`LedgerService`** only (no logic in Blade); see `docs/18-payment-infrastructure.md`
- **B2C customer portal:** `customers` + `customer` guard (`/customer/*`), saved travelers, linked bookings (`customer_id`), pay + voucher/invoice, admin **link customer email** on booking; see `docs/19-b2c-customer-portal.md`
- **CRM / sales pipeline:** extends **`inquiries`** with **`pipeline_stage`**, **`estimated_value`**, **`last_contacted_at`**; **`inquiry_activities`** (calls, notes, status/pipeline changes, follow-up completion) and **`inquiry_follow_ups`** (due dates, assignee, `reminder_sent_at` for future reminders); **`InquiryCrmService`** + admin **`InquiryCrmController`**; see `docs/20-crm-sales-pipeline.md`

## Layering principle

- Routes are split per module.
- Controllers orchestrate only.
- Business logic belongs to services/actions/repositories.
