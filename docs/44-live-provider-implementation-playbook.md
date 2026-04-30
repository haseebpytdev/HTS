# Live Provider Implementation Playbook (Phase EA-13)

## Purpose

Provide a practical implementation and rollout guide for enabling live provider engines behind the existing control-panel-driven runtime.

## Scope

- Amadeus live engine (search, pricing, booking) in sandbox
- Travelport and Sabre live request pipeline scaffolds (auth + transport + endpoint wiring)
- token lifecycle standards
- observability and rollout controls

## Architecture guardrails

- Keep vendor-specific logic in `App\Integrations\{Supplier}\`.
- Keep controller/service boundaries vendor-agnostic through DTOs/contracts.
- Never leak untyped vendor payloads outside mapper/adapter boundaries.
- Respect runtime control plane: module activation, tenant/provider access, operation flags, priority/fallback.

## Provider implementation order

1. **Amadeus first** (REST-only, fastest path to live validation)
2. **Travelport second** (REST with higher payload/flow complexity)
3. **Sabre third** (SOAP/REST hybrid readiness and richer edge handling)

## Current implementation status

- **EA-13.1:** Amadeus live search/pricing/booking foundation wired with runtime flags.
- **EA-13.2:** Travelport adapters now include provider-specific mapping and normalized error wrapping.
- **EA-13.3:** Sabre SOAP client foundation added; search path can switch SOAP/REST by configuration.
- **EA-13.4:** Sabre SOAP pricing/booking envelope builders, fault-aware SOAP handling, and masked raw SOAP exchange logging added for search/pricing/booking SOAP paths.
- **EA-13.5:** Sabre SOAP response mappers upgraded for itinerary/fare/PNR fidelity with fixture-based validation for search, pricing, and booking decoded SOAP payloads.
- **EA-13.6:** Added multi-leg/mixed-cabin SOAP fixture matrix, booking retrieve/cancel SOAP fixture coverage, and mapper invariants for status normalization and fare consistency.
- **Step 1 hardening:** Booking lifecycle orchestration now records explicit stages (`revalidation_passed`, `pnr_created`, `ticketing_pending`, `ticketed`, `cancelled`, `amended`) with stricter pre-booking checks for price validation, seat availability, and fare-rule confirmation.
- **Step 1.2 wiring:** Orchestrator ticket/amend now call provider-specific contracts when implemented; Amadeus, Travelport, and Sabre booking adapters now expose dedicated ticket/amend execution paths (with provider endpoint control and normalized adapter error handling).

## Token lifecycle standard

- Use centralized provider auth services (`AbstractSupplierAuthService` lineage).
- Support:
  - cached token reuse
  - refresh-before-expiry
  - one replay on 401
  - optional DB token persistence when enabled
- Do not request token per API call.

## Runtime activation controls

- Provider live calls should be gated by provider config (`*_LIVE_ENABLED`) and module/tenant runtime policy.
- Keep fallback to non-live behavior available for safe rollout.
- Enable per provider only after:
  - credentials validated
  - sandbox health test passes
  - approvals completed for production-risk actions

## Observability and audit requirements

For live requests:

- attach correlation ID
- capture HTTP status + latency
- persist request/response exchange via raw exchange action
- keep sensitive payload details restricted by access controls

## Amadeus live flow checklist

### Search

- Build query via `AmadeusFlightSearchPayloadBuilder`.
- Call configured search endpoint.
- Normalize offers via `AmadeusFlightOfferMapper`.

### Pricing

- Decode provider offer reference or use supplied opaque provider offer.
- Call pricing endpoint.
- Normalize via `AmadeusPriceBreakdownMapper`.

### Booking

- Build booking payload from normalized booking intent.
- Call booking create endpoint.
- Normalize via `AmadeusBookingMapper`.

## Travelport/Sabre scaffold checklist

- Auth service issues and caches real tokens.
- Client sends authenticated live requests when enabled.
- Adapters route to configured endpoints with correlation headers.
- Mapper extension is isolated and can be completed incrementally.

## Test plan

- Unit tests:
  - payload builders
  - mapper normalization from provider payload samples
  - token caching/retry behavior
- Feature tests:
  - integration endpoints with provider simulator bindings
  - fallback/multi-provider behavior
  - provider-unavailable and auth failure envelopes

## Rollout procedure

1. Enable live mode in sandbox for one provider.
2. Run smoke path (search -> pricing -> booking).
3. Monitor logs and error rates.
4. Gradually assign tenant access for pilot tenants.
5. Repeat per provider.

## Common failure patterns

- credentials valid but wrong environment endpoint
- stale token reuse due incorrect expiry parsing
- incomplete mapper coverage for real payload variants
- pricing/booking payload missing provider-specific required fields

## Recovery strategy

- disable live mode flag for impacted provider
- reduce provider priority / force fallback provider
- keep tenant access unchanged where possible
- capture incident evidence and update mapper/adapter logic

