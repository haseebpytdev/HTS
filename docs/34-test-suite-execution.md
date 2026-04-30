# Test suite execution guide (RF4.8)

## Goal

Define three practical execution tiers so release decisions are not based on one undifferentiated full run.

- **Smoke**: fast, high-signal safety checks for core flows.
- **Release-critical regression**: pre-launch gate for critical domains.
- **Full**: everything.

## Commands

From `apnasafar-portal/`:

| Suite | Composer command | Typical runtime | Use when |
|---|---|---:|---|
| Smoke | `composer test:smoke` | fast | local sanity before pushing/merging |
| Release-critical regression | `composer test:regression-critical` | medium/long | pre-release, RC branch, staging sign-off |
| Full | `composer test:full` | longest | nightly, final go-live confidence, broad refactors |

## Suite scope

## 1) Smoke suite

Coverage intent: quick confidence on auth, route-level admin access, quotations, booking create/confirm, payment basics, public package/group, integration API basics.

Current mapping (representative):

- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RoleAccessTest.php`
- `tests/Feature/Admin/AdminQuotationFlowTest.php`
- `tests/Feature/Booking/BookingEngineFlowTest.php`
- `tests/Feature/Admin/AdminBookingPaymentHttpTest.php`
- `tests/Feature/Frontend/PackageAndGroupFilterTest.php`
- `tests/Feature/Api/IntegrationsEndpointsTest.php`

Execution behavior:

- Uses `--stop-on-failure` to fail fast and keep local loop short.

## 2) Release-critical regression suite

Coverage intent: all release-critical domains identified in `docs/32-release-critical-test-matrix.md`.

Includes:

- auth/roles
- quotation + calculator
- bookings + payment/ledger/margin
- agency/customer isolation
- tenancy guards
- integration contract/orchestration/revalidation
- document lifecycle
- CRM inquiry flow
- support desk workflow
- settings/approval-sensitive paths

Execution behavior:

- Uses `--stop-on-failure` because first critical failure already blocks go-live.
- Run this suite on a clean DB state/environment before sign-off.

## 3) Full suite

Coverage intent: complete project test inventory.

- Runs `php artisan test`.
- Use for broad refactors and final confidence after critical/regression pass.

## Recommended release cadence

1. During development: run `composer test:smoke` frequently.
2. Before merge to release branch: run `composer test:regression-critical`.
3. Before tag/deploy: run `composer test:full` (or CI equivalent if available).
4. Pair with manual UAT checklists in `docs/07-manual-test-checklists/*`.

## Notes

- These commands intentionally avoid redesigning the test runner.
- If suite membership changes, update:
  - `composer.json` scripts
  - this document
  - `docs/32-release-critical-test-matrix.md` (if domain criticality changed)
