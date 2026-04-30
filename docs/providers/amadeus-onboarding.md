# Amadeus Onboarding (Live Integration)

## Purpose

Implementation-ready checklist for enabling Amadeus live integration once credentials are available.

## Required environment variables

- `INTEGRATIONS_DRIVER=amadeus` (or per-request `provider=amadeus`)
- `SUPPLIER_CREDENTIAL_ENV=test|production`
- `SUPPLIER_TOKEN_REFRESH_BUFFER_SECONDS=300`
- `SUPPLIER_HTTP_TIMEOUT_SECONDS=30`
- `INTEGRATIONS_USE_DATABASE_CREDENTIALS=true|false`
- `INTEGRATIONS_PERSIST_TOKENS_TO_DATABASE=true|false`

Amadeus-specific:
- `AMADEUS_BASE_URL_TEST`
- `AMADEUS_BASE_URL_PRODUCTION`
- `AMADEUS_TOKEN_PATH`
- `AMADEUS_CLIENT_ID_TEST`
- `AMADEUS_CLIENT_SECRET_TEST`
- `AMADEUS_CLIENT_ID_PRODUCTION`
- `AMADEUS_CLIENT_SECRET_PRODUCTION`

## Config keys to verify

- `config/integrations.php`
- `config/amadeus.php`
- `config/supplier_integration.php` (if still used by legacy wiring)

## Token flow

1. `AmadeusAuthService` resolves credentials using environment selector.
2. Requests OAuth token from Amadeus token endpoint.
3. Persists in cache (and DB optionally).
4. Uses shared one-time auth replay on 401.

## Base URLs

- Sandbox/Test: `AMADEUS_BASE_URL_TEST`
- Production: `AMADEUS_BASE_URL_PRODUCTION`
- Token path: `AMADEUS_TOKEN_PATH`

## Expected scopes and headers

Headers:
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`
- `X-Correlation-Id: {uuid}`

Scopes:
- Validate shopping/pricing/booking scopes with account manager.

## Test search payload example

```json
{
  "origin": "KHI",
  "destination": "JED",
  "departure_date": "2026-06-01",
  "adults": 1,
  "provider": "amadeus"
}
```

## Pricing/revalidation prerequisites

- Must revalidate Amadeus-origin offer reference.
- Preserve required context if adapter expects search session metadata.
- Confirm revalidation status before booking.

## Booking prerequisites

- Booking guarded by fresh successful revalidation snapshot.
- Provide traveler fields and contact details in normalized internal payload.
- Maintain idempotency key and correlation ID in requests.

## Error handling notes

- Never leak raw Amadeus payloads outside integration layer.
- Normalize to `ApiErrorData` and propagate via `SupplierIntegrationException`.
- Keep endpoint responses in strict error envelope schema.

## Rate-limit assumptions

- Assume tier-based throttling and sandbox differences from production.
- Monitor latency/status per provider call and adjust client pacing.

## Timeout and retry rules

- Timeout baseline: 30s; tune with observed response times.
- Retry policy:
  - one 401 replay only
  - optional safe-read retry for search/pricing
  - no blind retry on booking writes

## Suggested enablement sequence

1. Validate OAuth and token refresh handling.
2. Enable search calls and verify normalized offer DTO integrity.
3. Enable pricing/revalidation; verify fallback and guard logging.
4. Enable booking last with strict idempotency and observability checks.
