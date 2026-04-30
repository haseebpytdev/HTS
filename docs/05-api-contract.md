# Integration platform — internal API (`/api/v1`)

## API contract summary (quick read)

- **Base URL:** `{APP_URL}/api/v1` (see `routes/api.php`).
- **Envelope:** JSON; successful resources use a top-level `data` key via Laravel API Resources; paginated lists add `links` and `meta`. Errors: `422` with `message` + `errors`.
- **Catalog:** `GET` packages, groups, hotels, visa-rates, transport-rates (query filters per endpoint).
- **Quotations:** `GET /quotations/{id}`, `POST /quotations` (Umrah calculator payload; shared with admin validation).
- **Leads:** `POST /inquiries` (`source`: `quote` | `package` | `group`).
- **Health:** `GET /health`.
- **GDS (same version prefix):** `POST /integrations/flight-search`, `flight-pricing`, `booking` — portal integration layer only; see table below and `docs/10-supplier-integration-layer.md`.
- **Auth today:** largely open; lock down with Sanctum/API tokens before internet exposure (`docs/16-apnasafar-integration.md`).
- **DB / deploy context:** `docs/15-db-schema-summary.md`, `docs/14-environment-and-deployment.md`.

Phase **11** is named **Integration Platform + External Supplier API Layer**. It has two parts; the sections below detail **sub-layer 1** first, then point to sub-layer 2.

## Sub-layer 1 — Internal API (this document)

The **internal API** is Hayat Travel Solutions Portal’s **stable, versioned HTTP surface** for:

- First-party **frontend** (AJAX/mobile clients later, or split stacks).
- **Admin** and **B2B agency** tooling when you want machine-readable access instead of Blade-only flows.
- **Future Hayat Travel Solutions** product sync (push/pull catalog, quotes, inquiries) without ad-hoc SQL or shared DB assumptions.

Routes live in `routes/api.php` under the `/api` prefix, grouped as **`/api/v1/...`**.

**Rule:** Other modules call **your** JSON API or domain services inside the monolith first; they must **not** call Travelport/Sabre/Amadeus directly. GDS traffic goes through **sub-layer 2** (see `docs/10-supplier-integration-layer.md`).

## Response shape

- **Success (resources):** Laravel API Resources wrap payloads in a top-level `data` key. Paginated list responses also include `links` and `meta` (Laravel default pagination JSON).
- **Health check:** `GET /api/v1/health` returns `{ "data": { "status": "ok", "module": "api-v1" } }`.
- **Validation errors:** HTTP 422 with Laravel’s standard `{ "message": "...", "errors": { ... } }` object.

## Authentication

- **Current:** Read and write endpoints are **unauthenticated** (suitable for trusted server-to-server integration once secured with tokens or network rules).
- **Future:** Add Sanctum/API tokens or OAuth and middleware on `routes/api.php` without changing URL versioning.

## Endpoints

| Method | Path | Name | Description |
|--------|------|------|-------------|
| GET | `/api/v1/health` | `api.v1.health` | Liveness / version smoke check |
| GET | `/api/v1/packages` | `api.v1.packages.index` | Active packages; supports `q`, `destination`, `category`, `price_min`, `price_max`, `sort`, `per_page` |
| GET | `/api/v1/groups` | `api.v1.groups.index` | Groups; supports `q`, `status`, `departure_from`, `departure_to`, `sort`, `per_page` |
| GET | `/api/v1/hotels` | `api.v1.hotels.index` | Active hotels; optional `agency_id`, `q`, `per_page` |
| GET | `/api/v1/visa-rates` | `api.v1.visa-rates.index` | Active visa rates; optional `agency_id`, `per_page` |
| GET | `/api/v1/transport-rates` | `api.v1.transport-rates.index` | Active transport rates; optional `agency_id`, `per_page` |
| GET | `/api/v1/quotations/{id}` | `api.v1.quotations.show` | Single quotation with agency and line items |
| POST | `/api/v1/quotations` | `api.v1.quotations.store` | Create quotation using the same Umrah calculator payload as admin (see `UmrahQuotationPayloadRules`) |
| POST | `/api/v1/inquiries` | `api.v1.inquiries.store` | Create lead inquiry; `source` is `quote`, `package`, or `group`; response includes CRM fields (`pipeline_stage`, `estimated_value`, `last_contacted_at`) — see `docs/20-crm-sales-pipeline.md` |

### Sub-layer 2 — Integration routes (same `/api/v1` prefix)

| Method | Path | Name | Description |
|--------|------|------|-------------|
| POST | `/api/v1/integrations/flight-search` | `api.v1.integrations.flight-search.store` | Search via configured GDS driver; normalized offers |
| POST | `/api/v1/integrations/flight-pricing` | `api.v1.integrations.flight-pricing.store` | Price / revalidate flow |
| POST | `/api/v1/integrations/booking` | `api.v1.integrations.booking.store` | Booking create (scaffold / provider-dependent) |

Full contract and JSON rules: **`docs/10-supplier-integration-layer.md`**, **`docs/12-json-handling-rules.md`**.

## Implementation map (internal API)

- Controllers: `app/Http/Controllers/Api/V1/`
- Form requests: `app/Http/Requests/Api/V1/`
- Transformers: `app/Http/Resources/Api/V1/`
- Shared quotation validation: `app/Http/Requests/Support/UmrahQuotationPayloadRules.php` (used by admin `StoreQuotationRequest` and API `StoreQuotationApiRequest`)
- Inquiry creation: `app/Actions/Inquiry/CreateLeadInquiryAction.php` (shared with frontend inquiry forms); CRM timeline/pipeline: `App\Services\Crm\InquiryCrmService` (`docs/20-crm-sales-pipeline.md`)
- Public **`uuid`** on inquiries and quotations in JSON resources (mobile-safe identifiers); see `docs/22-schema-baseline-and-api.md`
- Quotation creation: `app/Actions\Admin\UpsertQuotationAction` (shared with admin)

## Sub-layer 2 — Supplier integration (separate doc)

See **`docs/10-supplier-integration-layer.md`** for **provider-agnostic integration contracts** (`App\Contracts\Integrations\*`), driver configuration (`SUPPLIER_GDS_DRIVER`), and per-supplier adapters under `App\Integrations\`.

## Tests

- `tests/Feature/Api/V1EndpointsTest.php`
- `tests/Feature/Api/IntegrationsEndpointsTest.php`
- `tests/Feature/Api/IntegrationSearchSnapshotTest.php`
