# Supplier integration layer (GDS)

Phase **11 — Integration Platform + External Supplier API Layer** includes this **sub-layer 2**, separate from the **internal HTTP API** documented in `docs/05-api-contract.md`.

**Mandatory JSON and observability rules:** `docs/12-json-handling-rules.md` (no raw JSON in controllers; DTOs + mappers; raw archive; correlation IDs; retry policy; config-driven URLs/credentials).

## Success condition (final rule for this phase)

**Switching the active supplier for flight search** (e.g. Amadeus → Sabre → Travelport) must require **only provider selection and configuration** — `INTEGRATIONS_DRIVER`, optional per-request `provider`, env/credential tables, and container bindings for that driver — **not** rewrites of:

| Must not require changes when swapping provider | Why |
|--------------------------------------------------|-----|
| **Controllers** | They call orchestrators / contracts; they never reference vendor classes or raw supplier JSON. |
| **Quotation logic** | Umrah calculator and quote persistence stay on **internal** pricing inputs; any future GDS-assisted flight leg consumes **normalized DTOs**, not vendor payloads. |
| **UI** | Blade/components bind to **your** fields and DTO-backed API shapes, never Travelport/Sabre/Amadeus JSON keys. |
| **Database schema** | Domain tables and integration observability are **provider-agnostic** (e.g. `provider` as data, not separate schemas per GDS). Switching drivers is a **runtime/config** change, not a migration to swap suppliers. |

If a change of supplier would force edits in any of those four areas, the integration boundary is in the wrong place — move vendor specifics into `App\Integrations\{Supplier}\` and keep contracts + DTOs stable.

## Architecture

Use **provider-agnostic contracts** in `App\Contracts\Integrations\`. Application code (controllers, actions, jobs, internal API) inject these interfaces only—**never** vendor SDKs or vendor-specific HTTP clients outside the integration folders.

| Contract | Responsibility |
|----------|------------------|
| `FlightSearchProviderInterface` | Flight shopping / availability search |
| `FlightPricingProviderInterface` | Fare revalidation / price check |
| `BookingProviderInterface` | Create, retrieve, cancel booking (ancillary/seat map later) |
| `AuthTokenProviderInterface` | Token acquisition and refresh |

Each **supplier** has its own namespace under `App\Integrations\{Supplier}\` with:

- `*Client.php` — low-level HTTP / endpoint access (no domain rules).
- `*AuthService.php` — implements `AuthTokenProviderInterface` for that supplier.
- `*FlightSearchAdapter.php` — implements `FlightSearchProviderInterface`.
- `*FlightPricingAdapter.php` — implements `FlightPricingProviderInterface`.
- `*BookingAdapter.php` — implements `BookingProviderInterface`.

**Stub** implementations live in `App\Integrations\Stub\` for local/dev when `INTEGRATIONS_DRIVER` / `SUPPLIER_GDS_DRIVER` is `stub`.

## Provider isolation (Travelport, Sabre, Amadeus)

**Travelport, Sabre, and Amadeus are not interchangeable.** Each has its own **authentication flow**, **token lifetime and refresh semantics**, **sandbox vs production URLs and credentials**, and **request/response JSON (or mixed) shapes**. None of that is assumed to align across vendors.

- **Shared surface only:** Application code depends on **`App\Contracts\Integrations\*`** and **`App\Data\Integrations\*`**. Every vendor-specific detail stays inside **`App\Integrations\{Travelport|Sabre|Amadeus}\`** (`*AuthService`, `*Client`, `Payloads/`, `Mappers/`, adapters).
- **No structural assumptions:** Do **not** write parsers, mappers, or helpers that expect one supplier’s JSON keys, nesting, or error envelope to match another’s. If two responses look similar, still implement **separate** mapping paths per provider (shared code may only operate on **already-normalized DTOs**).
- **Auth and config per vendor:** Token endpoints, grant types, headers, and expiry handling are implemented **per** `*AuthService` and **per** `config/{travelport,sabre,amadeus}.php` (plus env / optional DB credentials). Do not reuse one vendor’s token response parsing for another.
- **Orchestration chooses the implementation, not the wire format:** `IntegrationOrchestrationService` / registry swap **which adapter** runs; they do **not** normalize raw supplier JSON—that remains the job of the selected provider’s adapters and mappers.

## Normalized domain layer (required)

**Do not** pass raw supplier JSON to controllers, Blade, or internal API resources. The flow is:

1. Application builds an **internal request DTO** (e.g. `FlightSearchRequestData`, `BookingCreateRequestData`).
2. The **adapter** calls the vendor `*Client`, receives **raw JSON** (or XML) **only inside** `App\Integrations\{Supplier}\`.
3. A **mapper** (private methods on the adapter, or dedicated mapper classes) converts vendor payloads into **normalized DTOs**.
4. Contracts return **only** those DTOs (`FlightOfferData`, `PriceBreakdownData`, `BookingData`, etc.).

| DTO | Role |
|-----|------|
| `FlightSearchRequestData` | Internal search request |
| `FlightOfferData` | One priced or unpriced itinerary option |
| `FlightSegmentData` | Segment row inside an offer |
| `PriceBreakdownData` | Fare / tax / total after revalidation or pricing |
| `BookingCreateRequestData` | Passengers + offer reference for `createBooking` |
| `BookingData` | Create / retrieve / cancel result |
| `TravelerData` | Passenger normalized fields |
| `ApiErrorData` | Stable error surface; map vendor failures here, then throw `SupplierIntegrationException` with optional `apiError` |
| `NormalizedPayloadMetadata` | Version stamp on mapper output (`provider`, `provider_api_family`, `provider_version`, `mapper_version`, `normalized_schema_version`) — see **JSON schema versioning** below |

## JSON schema versioning (internal)

Every mapper should attach **`NormalizedPayloadMetadata`** (via `FlightOfferData::$metadata`, `PriceBreakdownData::$metadata`, `BookingData::$metadata`) so persisted JSON can be migrated when suppliers or normalized shapes change.

- **Factory:** `NormalizedPayloadMetadata::forSchemaKey($provider, $schemaKey)` with `$schemaKey` one of `flight_offer`, `price_breakdown`, `booking`.
- **Config:** `config/integration_mapping.php` — per-provider `provider_api_family`, `provider_version`, `mapper_version`, plus global `normalized_schemas.*` identifiers (e.g. `flight_offer.v1`).
- **Serialization:** DTOs implement `JsonSerializable`; when `metadata` is set, the five stamp fields appear **before** business keys in the encoded array (same shape as a flat JSON document).

## Layout (examples)

```
app/Contracts/Integrations/
  FlightSearchProviderInterface.php
  FlightPricingProviderInterface.php
  BookingProviderInterface.php
  AuthTokenProviderInterface.php

app/Integrations/Shared/
  AbstractSupplierAuthService.php
  SupplierJsonHttpClient.php
  SupplierTokenCache.php
  Exceptions/ (ProviderAuthException, SupplierIntegrationException, …)

app/Integrations/Travelport/
  TravelportClient.php
  TravelportAuthService.php
  TravelportFlightSearchAdapter.php
  TravelportFlightPricingAdapter.php
  TravelportBookingAdapter.php

app/Integrations/Sabre/
  SabreClient.php
  SabreAuthService.php
  SabreFlightSearchAdapter.php
  SabreFlightPricingAdapter.php
  SabreBookingAdapter.php

app/Integrations/Amadeus/
  AmadeusClient.php
  AmadeusAuthService.php
  AmadeusFlightSearchAdapter.php
  AmadeusFlightPricingAdapter.php
  AmadeusBookingAdapter.php

app/Data/Integrations/
  CachedSupplierToken.php
  FlightSearchRequestData.php
  FlightOfferData.php
  FlightSegmentData.php
  PriceBreakdownData.php
  BookingCreateRequestData.php
  BookingData.php
  TravelerData.php
  ApiErrorData.php
  NormalizedPayloadMetadata.php

config/
  integration_mapping.php
```

## Auth and token management

- **One auth service per provider:** `TravelportAuthService`, `SabreAuthService`, `AmadeusAuthService` extend **`App\Integrations\Shared\AbstractSupplierAuthService`** and implement **`AuthTokenProviderInterface`**.
- **Central cache:** **`App\Integrations\Shared\SupplierTokenCache`** stores tokens under `supplier_token:{provider}:{credential_environment}` using the app cache store.
- **Expiry:** **`App\Data\Integrations\CachedSupplierToken`** holds `expiresAt`; OAuth responses map `expires_in` (Amadeus) or defaults (Travelport often ~24h when omitted in config).
- **Refresh before expiry:** `token_refresh_buffer_seconds` (default **300**) — `getAccessToken()` / `refreshTokenIfNeeded()` only hit the network when the cache is empty or the token is within the buffer of expiry (not on every adapter call).
- **Single-flight:** `Cache::lock` around token fetch reduces stampedes when the cache expires.
- **Retry once on 401:** **`executeWithAuthRetry(callable $operation)`** runs the operation; on **`SupplierAuthException`** with HTTP **401**, it **`invalidateCachedToken()`** and runs the operation **once** more.
- **Test vs production credentials:** **`SUPPLIER_CREDENTIAL_ENV`** (`test` \| `production`) switches vendor config `credentials.test` vs `credentials.production`. Optional DB-backed overrides when **`INTEGRATIONS_USE_DATABASE_CREDENTIALS`** is true (see **`ProviderCredentialResolver`**).
- **Stub driver:** **`StubAuthTokenAdapter`** returns a static access token and does not use HTTP.

Low-level HTTP clients should send **`Authorization: Bearer {token}`** from **`getAccessToken()`**, or wrap outbound calls in **`executeWithAuthRetry()`** once 401 handling is wired.

## Configuration

- **Config:** `config/integrations.php` (primary), `config/supplier_integration.php` (aggregate / compatibility), per-vendor `config/travelport.php`, `config/sabre.php`, `config/amadeus.php`, `config/integration_mapping.php`
- **Env:** `INTEGRATIONS_DRIVER` (fallback: `SUPPLIER_GDS_DRIVER`) — `stub` (default), `travelport`, `sabre`, `amadeus`
- **`SUPPLIER_CREDENTIAL_ENV`**, **`SUPPLIER_TOKEN_REFRESH_BUFFER_SECONDS`**, per-vendor `base_url`, `token_path`, and `credentials.test` / `credentials.production` — see `.env.example`
- **Bindings:** `AppServiceProvider` registers each **interface** → the adapter for the active driver. Vendor `*Client` and `*AuthService` are **singletons** so tokens stay consistent across adapters. **`SupplierTokenCache`** is registered as a singleton.

## Data transfer

- **Search request:** `App\Data\Integrations\FlightSearchRequestData`
- **Contract return types:** `list<FlightOfferData>`, `PriceBreakdownData`, `BookingData` — not `array` bags of vendor fields.

## Error normalization

- Throw **`App\Integrations\Shared\Exceptions\SupplierIntegrationException`** (or sibling types under **`App\Integrations\Shared\Exceptions\`**) after mapping vendor errors to stable `normalizedCode`, optional `supplierContext`, and optional **`ApiErrorData`**. Do not expose raw vendor payloads to the UI unchecked. See **`docs/12-json-handling-rules.md`**.
- Integration API endpoints under **`/api/v1/integrations/*`** return a strict, provider-agnostic error envelope:
  - `error.code`
  - `error.message`
  - `error.supplier_code`
  - `error.correlation_id`
  - `error.http_status`
  mapped from `ApiErrorData`.

## Relationship to internal API

- **`/api/v1/*`** exposes **your** domain. A future `flight-search` endpoint would inject **`FlightSearchProviderInterface`** and call `searchFlights()`—not `Travelport\*` classes directly.

## Observability and persistence

Persist **raw** request/response pairs and **normalized** search/booking snapshots in separate tables so vendor payload drift can be audited. See **`docs/11-integration-observability.md`**.

## Tests

- `tests/Unit/Integration/SupplierIntegrationBindingsTest.php`
- `tests/Unit/Integration/NormalizedPayloadMetadataTest.php`
- `tests/Unit/Integration/SupplierAuthTokenManagementTest.php`
- `tests/Unit/Integration/SupplierJsonHttpClientTest.php`
- `tests/Unit/Integration/IntegrationOrchestrationServiceTest.php`
- `tests/Unit/Integration/FakeProviderPayloadMapperTest.php`
- `tests/Feature/Api/IntegrationSearchSnapshotTest.php`
- `tests/Feature/Integration/IntegrationObservabilityTest.php`
