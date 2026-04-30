# JSON handling rules (supplier / GDS integrations)

These rules apply to **all** outbound supplier traffic (Travelport, Sabre, Amadeus, and future providers) and to any code path that consumes supplier JSON.

Companion docs: `docs/10-supplier-integration-layer.md`, `docs/11-integration-observability.md`.

**Provider differences:** Authentication, tokens, environments, and **payload shapes** differ by vendor. Keep each implementation isolated under `App/Integrations/{Supplier}/` (e.g. `Travelport/`, `Sabre/`, `Amadeus/`) and expose only **shared contracts** and **normalized DTOs** outside that tree. **Never** assume one provider’s JSON structure matches another’s—even for the same business concept (e.g. “offer” or “segment”).

---

## 1. Never decode untyped JSON directly in controllers

- HTTP controllers, internal API controllers, and Blade must **not** `json_decode` supplier bodies or treat unstructured `array` blobs as domain data.
- Controllers call **orchestrators** or application services; those use **adapters** that return typed **`App\Data\Integrations\*`** objects.

## 2. Always map raw JSON into DTOs

- Decode and interpret vendor payloads **only** inside `App\Integrations\{Supplier}\` (via `SupplierJsonHttpClient`, `*Client`, and **mapper** classes).
- Anything leaving the integration layer toward the rest of the app must be a **DTO** (`FlightOfferData`, `BookingData`, `ApiErrorData`, etc.), not raw JSON.

## 3. Always keep raw request and response JSON for audit / debugging

- Persist or log through the observability pipeline: **`integration_request_logs`** / **`integration_response_logs`**, **`RecordIntegrationRawExchangeAction`**, and high-level **`integration_logs`** / **`IntegrationFlowRecorder`** (and related snapshot tables) as appropriate.
- When real GDS calls are enabled, do **not** drop raw payloads on success or failure.

## 4. Always validate required keys before mapping

- Mappers must verify required paths (explicit `isset` / structure checks, or a small dedicated validator step) **before** constructing DTOs.
- If required data is missing or malformed: throw a **typed** integration exception (e.g. `ProviderMappingException`) with **`ApiErrorData`** — do not emit half-filled DTOs for mandatory business fields.

## 5. Always normalize money, currency, times, airport / airline codes

- **Money:** consistent representation in DTOs (e.g. decimal string or documented minor-unit convention); never pass vendor-specific numeric scales to the UI unchecked.
- **Currency:** ISO 4217 where applicable.
- **Times:** normalize to a single convention (e.g. UTC `DateTimeImmutable` or ISO 8601 strings) in the mapper.
- **Airport / airline codes:** normalize (e.g. uppercase IATA) in the mapper, not in views.

## 6. Always create provider-specific mapper classes

- Use **`App\Integrations\{Supplier}\Mappers\`** classes implementing shared contracts where they exist (`FlightOfferMapperInterface`, etc.).
- Do not centralize all vendors in one unmaintainable mapper.

## 7. Always create a normalized error object

- Map supplier failures to **`ApiErrorData`** (stable codes/messages for callers).
- Throw **`SupplierIntegrationException`** (or other **`App\Integrations\Shared\Exceptions\*`** types) so the boundary never exposes raw vendor error JSON to the UI unchecked.
- For `/api/v1/integrations/*`, keep one envelope shape for all providers and operations: `error.{code,message,supplier_code,correlation_id,http_status}`.

## 8. Never couple UI fields to vendor response field names

- Blade, Livewire, and **internal** API resources must bind to **DTO** / application field names only — never to Travelport/Sabre/Amadeus JSON keys or paths.

## 9. Add correlation IDs to every external call

- Generate or propagate a **correlation** (and trace, if used) ID per outbound operation; include in persisted request/response logs and flow recorder / snapshot records.
- Pass through supplier-request headers when the vendor documents support for client correlation.

## 10. Use retries only for safe / idempotent operations

- **Appropriate:** idempotent reads, safe GET-style shopping calls where duplicates are harmless, and the **single** auth retry path (**401** invalidate + one replay via `executeWithAuthRetry`).
- **Inappropriate by default:** booking create, ticketing, payment-like POSTs, or any non-idempotent write — no blind multi-retry loops unless the vendor documents true idempotency and the team has explicitly approved it.

## 11. Separate test and production credentials

- Use **`SUPPLIER_CREDENTIAL_ENV`** (`test` \| `production`) and separate config entries per vendor.
- Optional DB-backed secrets via **`integration_connections`** / **`integration_credentials`** when **`INTEGRATIONS_USE_DATABASE_CREDENTIALS`** is enabled.

## 12. Never hardcode endpoints or credentials

- Base URLs, token paths, and secrets must come from **`config/*.php`**, **`.env`**, and/or encrypted credential tables — not string literals in adapters (except trivial relative segments assembled from config values).

## 13. Use config + env + credential tables

- Resolve credentials through **`ProviderCredentialResolver`** and vendor config files (`config/travelport.php`, `config/sabre.php`, `config/amadeus.php`, `config/integrations.php`); merge DB overrides when the feature flag allows.

## 14. Wrap all provider failures in internal exceptions

- Do not let raw HTTP client exceptions define the user-facing or API error shape; normalize auth, transport, rate limit, validation, and mapping failures into **`SupplierIntegrationException`** (or sibling types) plus **`ApiErrorData`** where applicable.

## 15. Log latency and HTTP status for every provider call

- For each outbound call, record **HTTP status**, **elapsed time**, and linkage to **correlation ID** in observability storage (aligned with **`SupplierHttpResponse`** and raw exchange / response log writers).

---

## Success condition (provider swap)

**Changing flight-search provider** (Amadeus ↔ Sabre ↔ Travelport) should require **only config / driver selection** (and the corresponding adapter registration), **not** rewriting **controllers**, **quotation logic**, **UI**, or **database schema**. Anything that would break that rule belongs inside `App/Integrations/{Supplier}/`, behind **`App\Contracts\Integrations\*`** and **`App\Data\Integrations\*`**.

---

## Quick checklist (PR review)

| Rule | Verify |
|------|--------|
| No raw JSON in controllers | Only DTOs / JsonResource from internal shapes |
| Mapper exists per supplier concern | Under `Integrations/{Supplier}/Mappers/` |
| Raw wire retained | Request/response logs or equivalent |
| Errors normalized | `ApiErrorData` + typed exception |
| Correlation ID | On call + logs |
| Retries | Only safe/idempotent + documented auth replay |
| Secrets / URLs | Config + env + DB — no hardcoding |
| Provider swap | Changing `INTEGRATIONS_DRIVER` / `provider` does not force controller, quote, UI, or schema edits |
