# Duffel Onboarding and Operations Guide

## Purpose

Operational guide to enable, validate, monitor, and support Duffel in current admin-first workflows (quotation, pricing, booking bridge) before public rollout.

## Runtime scope

- Internal admin workflows are primary (`admin.quotations.*`, `admin.bookings.*`).
- Public/B2C exposure is out of scope for this phase.
- Provider execution must continue through integration orchestration and normalized DTO boundaries.

## Environment modes

### Test mode (sandbox)

- Use `integrations.credential_environment=test`.
- Configure Duffel sandbox token in connection credentials or env-backed config.
- Run smoke path: search -> pricing -> booking with sandbox references.
- Keep `duffel.booking_live_enabled` toggled only for controlled UAT windows.

### Live mode (production)

- Use `integrations.credential_environment=production`.
- Ensure production token exists and connection health test succeeds before activation.
- Require approval-gated controls for high-risk module/credential changes in Super Admin.
- Roll out by tenant/provider access matrix (pilot tenants first).

## Control-plane prerequisites (Super Admin)

Validate all before enabling live bookings:

1. `admin.integrations.index` and provider connection screen:
   - Duffel connection is active.
   - Connection status is healthy.
   - Environment matches intended runtime.
2. `admin.integrations.access-matrix.index` and tenant provider access:
   - Duffel `is_enabled=true`.
   - Operation flags set per rollout stage (`can_search`, `can_price`, `can_book`).
   - Priority/fallback and multi-provider policy aligned to pilot plan.
   - Quota/soft-hard limits reviewed.
3. Module governance (`admin.modules.*`):
   - Duffel module status is active/connected.
   - Supported operations include required phase operations.

## Connection health checks

Operator checks:

- Run provider connection test from integration connection UI before any UAT run.
- If status is unhealthy/failed:
  - booking/pricing/search should return `integration_provider_unavailable`.
  - do not continue booking UAT until status returns healthy.

Monitoring references:

- Admin health pages:
  - `admin.monitoring.health-overview`
  - `admin.monitoring.integration-logs`

## Search observability checklist

Expected for each successful Duffel search:

- Raw request/response logs exist:
  - request operation `search_offer_request`
  - response status/latency recorded
- Normalized snapshots exist:
  - `supplier_search_sessions` row with correlation id
  - linked `supplier_offer_snapshots` rows
- Admin search snapshots viewable via:
  - `admin.integrations.search-results.index`
  - `admin.integrations.search-results.show`

## Pricing observability checklist

Expected for each Duffel pricing/revalidation:

- Raw request log operation `pricing` with response status and latency.
- Successful result is persisted in `BookingRevalidationGuard` snapshot cache.
- Pricing uses selected-offer context when supplied in opaque context.

## Booking observability checklist

Expected for successful Duffel order creation:

- Raw request log operation `booking_create` with response status/latency.
- `supplier_booking_snapshots` row persists normalized booking references and totals.
- For admin booking bridge flow:
  - `booking.internal_notes.supplier_booking_request` exists from quotation selection.
  - `booking.internal_notes.supplier_booking` contains provider, offer reference, order id, PNR, and status.

Expected for failed booking:

- API returns normalized `supplier_booking_failed` envelope.
- Failed raw exchange is retained with response status (for incident triage).

## Failed request monitoring runbook

When Duffel incident is reported:

1. Filter `admin.monitoring.integration-logs` by provider `duffel` and recent timeframe.
2. Group by correlation id and identify failing operation (`search_offer_request`, `pricing`, `booking_create`).
3. Verify:
   - response status code trend
   - latency spikes
   - normalized API error code seen by caller
4. Cross-check governance:
   - tenant access matrix flags
   - module status and supported operations
   - connection health
5. If provider instability is confirmed:
   - disable Duffel booking operation at tenant/provider access level or module operation level
   - lower provider priority or disable fallback depending on incident plan
   - keep evidence (correlation ids, status, timestamps) for postmortem

## Operator runbook (admin-first flow)

1. Confirm Duffel connection health in Super Admin.
2. In admin quotation flow, populate supplier flight selection:
   - provider `duffel`
   - offer reference
   - optional correlation id / selected passenger ids
3. Convert quotation to booking draft.
4. Confirm booking from admin booking screen.
5. Validate outcomes:
   - booking supplier hook status
   - supplier booking metadata in internal notes
   - raw log entries for pricing and booking

## UAT completion criteria for Duffel

- Connection health test passes in target environment.
- One end-to-end successful admin journey:
  - search snapshot
  - pricing/revalidation
  - booking creation
- One controlled failure journey:
  - normalized failure envelope returned
  - failed request visible in monitoring logs
- Tenant denial and module-operation denial behaviors verified.
