# Live Supplier Integration Checklist

## Goal

Single operational checklist for onboarding Travelport, Sabre, and Amadeus credentials into the existing integration contract layer.

## Pre-onboarding prerequisites

- Contracts/DTO boundaries stable (`App\Contracts\Integrations\*`, `App\Data\Integrations\*`).
- Fixture-driven tests passing for orchestration and endpoint behavior.
- Strict error envelope and correlation ID behavior validated.
- Raw request/response observability pipeline enabled.

## Common environment/config checklist

- Set:
  - `SUPPLIER_CREDENTIAL_ENV=test`
  - `SUPPLIER_HTTP_TIMEOUT_SECONDS`
  - `SUPPLIER_TOKEN_REFRESH_BUFFER_SECONDS`
  - `INTEGRATIONS_USE_DATABASE_CREDENTIALS`
  - `INTEGRATIONS_PERSIST_TOKENS_TO_DATABASE`
- Verify provider config files:
  - `config/travelport.php`
  - `config/sabre.php`
  - `config/amadeus.php`
  - `config/integrations.php`
- Confirm `supported_drivers` includes enabled providers.

## Provider onboarding sequence (repeat per supplier)

1. **Credentials**
   - Enter sandbox credentials first (env or credential tables).
   - Validate base URL + token URL correctness.
2. **Auth**
   - Verify token issuance and cache behavior.
   - Confirm single 401 replay path.
3. **Search**
   - Run one-way search with known route/date.
   - Verify normalized `FlightOfferData` fields + metadata.
4. **Pricing/Revalidation**
   - Revalidate offer from search output.
   - Verify successful status and amount normalization.
5. **Booking**
   - Ensure booking guard enforces fresh revalidation.
   - Validate successful booking snapshot persistence.
6. **Observability**
   - Verify correlation ID, status, latency, and raw payload capture.
7. **Operational limits**
   - Validate rate limits/timeouts in sandbox.
   - Tune timeout and safe retry behavior.

## API governance validation (internal integration API)

- `X-Integration-Key` required.
- `X-Idempotency-Key` required and replay behavior validated.
- Throttle (`integrations-api`) enforced.
- Errors always return strict internal envelope:
  - `error.code`
  - `error.message`
  - `error.supplier_code`
  - `error.correlation_id`
  - `error.http_status`

## Pricing/revalidation and booking guard checks

- Revalidation snapshots must be created on successful pricing.
- Booking without fresh revalidation must fail with `fresh_revalidation_required`.
- Booking after fresh revalidation must pass.

## Rate-limit and timeout policy baseline

- Initial baseline:
  - timeout = 30s
  - retry = single 401 replay + controlled safe-read retries only
  - no automatic retries for booking writes
- Recalibrate after sandbox/prod metrics are collected.

## Promotion to production checklist

1. Switch `SUPPLIER_CREDENTIAL_ENV=production`.
2. Rotate to production credentials and endpoints.
3. Re-run smoke path:
   - auth -> search -> pricing -> booking (small controlled test)
4. Confirm observability records in production environment.
5. Enable provider progressively (one provider at a time) with rollback plan.

## Links

- `docs/providers/travelport-onboarding.md`
- `docs/providers/sabre-onboarding.md`
- `docs/providers/amadeus-onboarding.md`
- `docs/10-supplier-integration-layer.md`
- `docs/12-json-handling-rules.md`
