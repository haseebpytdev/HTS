# Phase 15 — Testing and stabilization

**Goal:** Make the system safer to extend and integrate by locking in regressions for high-value flows.

**Test environment:** PHPUnit uses in-memory SQLite (`phpunit.xml`). Run: `php artisan test`.

## Coverage map

| Area | Tests | Notes |
|------|--------|--------|
| **Calculator math** | `tests/Unit/Calculator/UmrahQuotationCalculatorTest.php` | Markup modes, room/child logic, per-person visa |
| **Admin quotations** | `tests/Feature/Admin/AdminQuotationFlowTest.php` | POST create via `UpsertQuotationAction`, admin index `agency_id` filter |
| **Quotation dependencies (support)** | `tests/Support/CreatesUmrahQuotationDependencies.php` | Hotels/rates/visa/transport/flight rows for valid `StoreQuotationRequest` payloads |
| **Role access** | `tests/Feature/Auth/RoleAccessTest.php` | Guest redirects; admin vs agency; super admin / sales operator dashboard; agency blocked from admin quotations |
| **Agency isolation** | `tests/Feature/Agency/AgencyPortalAccessTest.php` | Quotations scoped by agency; inquiries index scoped by `agency_id` |
| **API output** | `tests/Feature/Api/V1EndpointsTest.php` | Health, packages, inquiries, quotations validation/show, **groups** `status` filter + JSON shape, **hotels** index shape |
| **Frontend filters** | `tests/Feature/Frontend/PackageAndGroupFilterTest.php` | Package `price_min`/`price_max`; group `status` |
| **Inquiry submission** | `tests/Feature/Frontend/FrontendInquirySubmissionTest.php` | Quote + package POST routes |
| **CRM / sales pipeline** | `tests/Feature/Admin/InquiryCrmTest.php` | Pipeline updates, calls/notes, follow-ups, status activity logging |

## Related fixes (stabilization)

- **Guest-safe navigation:** `resources/views/layouts/navigation.blade.php` wraps authenticated menu in `@auth` so public frontend routes do not call `Auth::user()->name` when logged out.
- **Null-safe catalog cards:** `public-package-card` and `public-group-card` use nullsafe `destination?` / `package?` for optional relations.

## Definition of done (this phase)

Important flows above have automated coverage; CI/local `php artisan test` passes. Extend this document when adding new modules.
