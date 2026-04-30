# Integrations UAT checklist

Purpose: cover live/staging integration checks and orchestration behavior that fixture automation cannot fully guarantee.

## Pre-check

- [ ] Automated integration suites pass (`composer test:regression-critical` or targeted integration subset).
- [ ] Provider credentials and environment flags are correct for current environment.
- [ ] Correlation id logging is enabled and visible in integration logs.

## Single-provider live checks

Repeat for Travelport, Sabre, Amadeus (as enabled):

- [ ] Flight search returns offers for known route/date.
- [ ] Flight pricing/revalidation returns stable totals for at least one offer.
- [ ] Booking attempt reaches expected outcome (success or known supplier rejection).
- [ ] Error envelope from any forced invalid request remains normalized (no raw vendor leakage).

### Duffel internal-workflow checks (mandatory before wider rollout)

- [ ] Duffel connection test passes in target environment (test/live as applicable).
- [ ] Duffel search returns offers for known route/date and creates search session + offer snapshots.
- [ ] Duffel pricing/revalidation succeeds for selected offer and guard snapshot is created.
- [ ] Admin quotation to booking flow can carry Duffel selection and produce supplier booking reference.
- [ ] Duffel failure case (forced invalid offer or simulated supplier error) returns normalized error envelope and is visible in integration logs.

## Multi-provider + fallback behavior

- [ ] Run multi-provider search with two or more providers enabled.
- [ ] Confirm response includes comparison object (`cheapest`, `fastest`, `best`).
- [ ] Temporarily disable one provider credential and verify partial-failure behavior is graceful.
- [ ] Confirm fallback behavior matches policy (`allow_fallback` true/false).

## Booking guard preconditions

- [ ] Attempt booking without fresh revalidation and verify blocked outcome.
- [ ] Revalidate then immediately book and verify allowed path.
- [ ] Verify provider mismatch does not bypass guard (revalidate provider A, book provider B should fail).
- [ ] For Duffel admin flow, confirm bridge auto-runs pricing guard path when supplier booking request exists but snapshot is missing.

## Snapshot/log persistence and auditability

- [ ] Verify search session rows are created with correlation id.
- [ ] Verify offer snapshots persist for successful search.
- [ ] Verify integration logs include status + latency.
- [ ] Verify failed provider attempts are logged with normalized codes.
- [ ] Verify Duffel operations appear with expected operation keys: `search_offer_request`, `pricing`, `booking_create`.
- [ ] Verify failed Duffel requests are discoverable from `admin.monitoring.integration-logs` by correlation id.

## Governance and denial checks (Duffel)

- [ ] Tenant access denial: disable Duffel for a tenant and confirm API/admin flow returns `integration_access_denied`.
- [ ] Module-operation denial: disable Duffel operation (search/pricing/booking) at module level and confirm `integration_provider_unavailable`.
- [ ] Connection-health denial: mark Duffel connection unhealthy/inactive and confirm `integration_provider_unavailable`.
- [ ] Quota denial: confirm configured hard-limit blocks for daily search/pricing or monthly booking when limit is exceeded.

## Real operator checks

- [ ] From admin flow, run a realistic quote -> booking path that depends on integration pricing.
- [ ] Confirm operators can identify which provider won/fell back in logs and UI.
- [ ] Confirm troubleshooting info is sufficient without developer intervention.
- [ ] Confirm Duffel runbook in `docs/providers/duffel-onboarding.md` is followed and evidence captured.

## Exit criteria

- [ ] At least one successful single-provider journey per enabled provider.
- [ ] Multi-provider fallback and comparison behavior validated.
- [ ] Guard, normalized errors, and observability checks pass with real credentials.
