# Hayat Travel Solutions integration notes (consumer systems)

Guidance for **another product or service** (e.g. future Hayat Travel Solutions apps) that consumes this portal’s APIs or syncs data with it.

## Two HTTP surfaces

1. **Internal JSON API** — `https://{portal-host}/api/v1/...`  
   Stable catalog, quotations, and inquiries. Documented in **`docs/05-api-contract.md`**.

2. **Supplier (GDS) integration API** — `https://{portal-host}/api/v1/integrations/...`  
   Flight search, pricing, and booking **through this portal’s integration layer** (not direct to Travelport/Sabre/Amadeus). Responses use **normalized DTOs** after mapping; raw vendor JSON is stored for audit inside the portal. See **`docs/10-supplier-integration-layer.md`** and **`docs/12-json-handling-rules.md`**.

## Recommended integration patterns

### Read catalog and post leads

- **GET** `/api/v1/packages`, `/api/v1/groups`, `/api/v1/hotels`, `/api/v1/visa-rates`, `/api/v1/transport-rates` for display or downstream pricing hints.
- **POST** `/api/v1/inquiries` to create leads with `source` = `quote` | `package` | `group` and optional foreign keys.
- **GET** `/api/v1/quotations/{id}` after staff or automation creates quotes (see below).

### Create quotations from automation

- **POST** `/api/v1/quotations` with the same payload rules as admin (`UmrahQuotationPayloadRules` / `StoreQuotationApiRequest`).  
- Implementation path: `UpsertQuotationAction` + `UmrahQuotationInputFactory` + `UmrahQuotationCalculator` — **no duplicate pricing math** in consumers.

### Flight shopping (when GDS is live)

- **POST** `/api/v1/integrations/flight-search` — send **portal-defined** search fields; read **normalized** offers (not raw Travelport/Sabre/Amadeus JSON).
- Include or propagate **`correlation_id`** when the client generates one (see integration controllers) for support and log correlation.

## Security (mandatory before public exposure)

- Today, many `/api/v1` routes are **unauthenticated** by design for trusted networks. Before exposing to the internet or third parties:
  - Add **Laravel Sanctum** personal access tokens, **API keys**, or **OAuth** middleware on `routes/api.php`.
  - Rate-limit integration endpoints.
  - Do **not** expose supplier credentials to the consumer; only this portal talks to GDS using `integration_connections` / env config.

## Versioning

- URLs are versioned (`/api/v1`). Breaking changes should introduce `/api/v2` while maintaining v1 for a deprecation window.

## Data ownership

- **Source of truth** for quotes, inquiries, and bookings remains this portal’s database unless you explicitly build bidirectional sync.
- For **read models** in another system, prefer periodic pull from `/api/v1/*` or event-driven sync (queue/webhooks — future work) over shared-database coupling.

## Code map for extenders

| Concern | Location |
|---------|----------|
| Internal API controllers | `app/Http/Controllers/Api/V1/` |
| Integration API controllers | `app/Http/Controllers/Api/V1/Integrations/` |
| Quotation math | `app/Services/Calculator/`, `app/Services/Quotation/UmrahQuotationInputFactory.php` |
| Inquiry creation | `app/Actions/Inquiry/CreateLeadInquiryAction.php` |
| GDS orchestration | `app/Services/Integrations/`, `App\Integrations\{Supplier}\` |

## Related

- **`docs/14-environment-and-deployment.md`** — env vars and deploy steps  
- **`docs/13-testing-stabilization.md`** — API regression tests (`V1EndpointsTest`, integration tests)
