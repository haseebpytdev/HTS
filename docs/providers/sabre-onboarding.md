# Sabre Onboarding (Live Integration)

## Purpose

Implementation-ready checklist for enabling Sabre live integration when credentials are issued.

## Required environment variables

- `INTEGRATIONS_DRIVER=sabre` (or per-request `provider=sabre`)
- `SUPPLIER_CREDENTIAL_ENV=test|production`
- `SUPPLIER_TOKEN_REFRESH_BUFFER_SECONDS=300`
- `SUPPLIER_HTTP_TIMEOUT_SECONDS=30`
- `INTEGRATIONS_USE_DATABASE_CREDENTIALS=true|false`
- `INTEGRATIONS_PERSIST_TOKENS_TO_DATABASE=true|false`

Sabre-specific:
- `SABRE_BASE_URL_TEST`
- `SABRE_BASE_URL_PRODUCTION`
- `SABRE_TOKEN_PATH`
- `SABRE_CLIENT_ID_TEST`
- `SABRE_CLIENT_SECRET_TEST`
- `SABRE_CLIENT_ID_PRODUCTION`
- `SABRE_CLIENT_SECRET_PRODUCTION`
- `SABRE_PCC` (if required for shopping/booking context)

## Config keys to verify

- `config/integrations.php`
- `config/sabre.php`
- `config/supplier_integration.php` (if compatibility path is in use)

## Token flow

1. `SabreAuthService` resolves credentials/environment.
2. Obtains token from Sabre token endpoint.
3. Stores token in cache and optionally DB.
4. Shared `executeWithAuthRetry()` handles single 401 replay.

## Base URLs

- Sandbox/Test: `SABRE_BASE_URL_TEST`
- Production: `SABRE_BASE_URL_PRODUCTION`
- Token endpoint path: `SABRE_TOKEN_PATH`

## Expected scopes and headers

Headers:
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`
- `X-Correlation-Id: {uuid}`

Additional headers may be account-dependent (for example partition/PCC metadata).

## Test search payload example

```json
{
  "origin": "KHI",
  "destination": "JED",
  "departure_date": "2026-06-01",
  "adults": 1,
  "provider": "sabre"
}
```

## Pricing/revalidation prerequisites

- Reuse a Sabre-origin offer reference from prior search.
- Provide `opaque_context` keys required by Sabre adapter mapping.
- Confirm returned status is successful before booking.

## Booking prerequisites

- Fresh revalidation snapshot required by booking guard.
- Traveler list must be complete and normalized.
- Keep idempotency keys unique for booking requests.

## Error handling notes

- Normalize Sabre errors to internal envelope via `ApiErrorData`.
- Throw `SupplierIntegrationException` (or sibling typed exceptions) inside integration layer.
- Controllers must stay vendor-agnostic.

## Rate-limit assumptions

- Assume strict account-tier limits until vendor confirms quotas.
- Apply conservative request pacing for search fan-out.

## Timeout and retry rules

- Timeout baseline: 30s; tune after sandbox latency measurements.
- Retry only:
  - single auth replay on 401
  - safe read operations only when policy allows
- No auto-retry on booking writes.

## Suggested enablement sequence

1. Validate auth in sandbox.
2. Enable search and verify DTO mappings + snapshots.
3. Enable pricing/revalidation and confirm fallback behavior.
4. Enable booking create after guard + idempotency verification.
