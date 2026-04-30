# Integration test strategy (RF4.6)

## Goal

Standardize integration and orchestration testing around deterministic fixtures so pre-launch regression runs are predictable and actionable.

## Test layers

- **API contract layer**: `tests/Feature/Api/*Integrations*Test.php`, `BookingRevalidationGuardTest.php`
- **Orchestration quality layer**: `tests/Feature/Integration/*`
- **Fixture consistency layer**: `tests/Feature/Integration/IntegrationFixtureConsistencyTest.php`
- **Fixture assets**: `tests/Fixtures/integrations/*`

## Fixture-driven simulation rules

- Providers are simulated via stub adapters (`StubFlightSearchAdapter`, `StubFlightPriceAdapter`, `StubBookingAdapter`).
- Scenario selection priority:
  1. pricing `opaque_context.scenario` (pricing only),
  2. `integrations.stub_provider_scenarios[provider]`,
  3. `integrations.stub_scenario`.
- Multi-provider tests must avoid brittle assumptions about provider list ordering unless ordering is explicitly guaranteed by policy.
- Use provider-specific fixtures for all failure paths (`auth_error`, `validation_error`, `fare_expired`, `rate_limit`, `timeout`) and success paths.

## Scenario matrix (expected behavior)

| Scenario | Endpoint flow | Expected status | Persistence / logging expectation |
|---|---|---|---|
| single-provider search success | `flight-search` | `200` | search session completed; offer snapshots and completed logs |
| multi-provider partial failure | `flight-search` (`multi_provider=true`) | `200` | failed provider entries captured; success provider logs persisted |
| no provider success (fallback allowed) | `flight-search` | `200` with empty offers | failed providers list present; no hard crash |
| pricing success | `flight-pricing` | `200` | revalidation snapshot recorded for booking guard |
| pricing fallback | `flight-pricing` with providers + fallback | `200` | `failed_providers` populated; winning driver returned |
| fare expired | `flight-pricing` | `409` | normalized error envelope (`fare_no_longer_available`) |
| auth / validation / timeout failures | search/pricing | `422`/`200(empty)`/`429` depending guard/rate-limit | normalized envelope or failed provider entry, with logs |
| booking guard missing revalidation | `booking` | `422` | error `fresh_revalidation_required` |
| booking guard provider mismatch | `booking` | `422` | revalidation snapshot does not cross provider keys |
| idempotency replay | same request key | same status | replay header set, no duplicate orchestration side effects |

## What “aligned with orchestration policy” means

- `allow_fallback=false` should stop on first provider result/failure in first position.
- `allow_fallback=true` should continue provider iteration and return merged outcomes.
- Comparison payload (`cheapest`, `fastest`, `best`) is required for multi-provider search.
- Tests should assert **presence and correctness** of comparison keys, not incidental provider ordering.

## Operational regression subset (recommended pre-launch)

Run at least:

- `tests/Feature/Api/IntegrationsEndpointsTest.php`
- `tests/Feature/Api/IntegrationSearchSnapshotTest.php`
- `tests/Feature/Api/BookingRevalidationGuardTest.php`
- `tests/Feature/Integration/SupplierOrchestrationQualityTest.php`
- `tests/Feature/Integration/IntegrationObservabilityTest.php`
- `tests/Feature/Integration/IntegrationFixtureConsistencyTest.php`

This subset validates contracts, fallback/revalidation policies, normalized failures, and fixture hygiene.
