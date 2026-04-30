# Test coverage map (Red Flag 4.1)

## Purpose

This document maps **existing** automated tests in **apnasafar-portal** to business domains so Red Flag 4 can prioritize gaps before go-live. It does **not** reflect PHPUnit code-coverage percentages—only **which areas have dedicated test classes** and how deep they appear to go.

## How tests run today

| Mechanism | Location / notes |
|-----------|------------------|
| **Runner** | PHPUnit (`vendor/bin/phpunit`) |
| **Suites** | `Unit` → `tests/Unit`, `Feature` → `tests/Feature` (`phpunit.xml`) |
| **DB** | SQLite `:memory:` with hybrid schema (migrations + `TestCase` fallbacks; see `docs/30-sqlite-test-harness-map.md`) |
| **Regression** | There is **no** separate “regression” suite label. A **pre-launch regression run** means executing the full **Unit + Feature** suites (and any future tagged groups) in a clean environment—trust depends on suite health and how well domains below are covered. |
| **CI** | No `.github/workflows` in this repo at the time of writing; automation of that run is **out of band** unless added later. |

## Coverage strength legend

- **Strong** — Multiple scenarios, touches real HTTP or service boundaries relevant to production risk.
- **Shallow** — Smoke, access-only, or single happy-path; valuable but not sufficient alone for go-live confidence.
- **Missing** — No dedicated test class found for the domain or subdomain.

**Unit** — Isolated class/service tests (may still use `RefreshDatabase` where noted).  
**Feature** — HTTP, routes, middleware, multi-component flows.  
**Integration (test category)** — External supplier/API behavior with fakes, cache, or orchestration (not PHPUnit’s `@group integration` unless stated).

---

## 1. Auth

| Strength | Type | Test class |
|----------|------|------------|
| Moderate → strong | Feature | `tests/Feature/Auth/AuthenticationTest.php` |
| Moderate | Feature | `tests/Feature/Auth/RegistrationTest.php` |
| Moderate | Feature | `tests/Feature/Auth/EmailVerificationTest.php` |
| Moderate | Feature | `tests/Feature/Auth/PasswordResetTest.php` (uses `Notification::fake`) |
| Shallow | Feature | `tests/Feature/Auth/PasswordConfirmationTest.php` |
| Shallow | Feature | `tests/Feature/Auth/PasswordUpdateTest.php` |
| Strong | Feature | `tests/Feature/Auth/RoleAccessTest.php` |
| Strong | Feature | `tests/Feature/Auth/PermissionSystemTest.php` |
| Shallow | Feature | `tests/Feature/ProfileTest.php` |

**Gaps / shallow areas**

- OAuth/social or custom SSO (if introduced) — not mapped here.
- Session fixation / concurrent sessions — no dedicated classes identified.
- Agency vs admin vs customer **login entrypoints** — partially implied by role tests; explicit matrix per guard may still need expansion.

---

## 2. Admin CRUD & admin operations

| Strength | Type | Test class | Focus (approx.) |
|----------|------|------------|-------------------|
| Strong | Feature | `tests/Feature/Admin/HotelCrudTest.php` | Hotels |
| Strong | Feature | `tests/Feature/Admin/HotelRoomTypeCrudTest.php` | Room types |
| Strong | Feature | `tests/Feature/Admin/HotelRateCrudTest.php` | Hotel rates |
| Strong | Feature | `tests/Feature/Admin/VisaTypeCrudTest.php` | Visa types |
| Strong | Feature | `tests/Feature/Admin/VisaRateCrudTest.php` | Visa rates |
| Strong | Feature | `tests/Feature/Admin/TransportTypeCrudTest.php` | Transport types |
| Strong | Feature | `tests/Feature/Admin/TransportRateCrudTest.php` | Transport rates |
| Strong | Feature | `tests/Feature/Admin/FlightEntryCrudTest.php` | Flight entries |
| Strong | Feature | `tests/Feature/Admin/PackageCrudTest.php` | Packages |
| Strong | Feature | `tests/Feature/Admin/GroupCrudTest.php` | Groups |
| Strong | Feature | `tests/Feature/Admin/InquiryOperationsTest.php` | Inquiry admin ops |
| Moderate | Feature | `tests/Feature/Admin/SettingsModuleTest.php` | Settings |
| Moderate | Feature | `tests/Feature/Admin/ExportHistoryRecordingTest.php` | Exports / history |
| Strong | Feature | `tests/Feature/Admin/AdminPermissionMatrixTest.php` | Route/action permissions |
| Moderate | Feature | `tests/Feature/Admin/TenantProviderAccessMatrixTest.php` | Tenant × provider UI gates |
| Moderate | Feature | `tests/Feature/Admin/IntegrationConnectionTestActionTest.php` | Connection test action |
| Moderate | Feature | `tests/Feature/Admin/AdminAnalyticsReportsTest.php` | Analytics reporting |
| Moderate | Feature | `tests/Feature/Admin/AdminBackgroundProcessingTest.php` | Queued jobs (scan, CSV) |

**Gaps**

- Any **admin modules without** a `*CrudTest` or dedicated feature class remain **missing** from automation until added.
- Bulk import/export edge cases beyond “recording” history — likely under-tested.

---

## 3. Quotation engine

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Unit | `tests/Unit/Calculator/UmrahQuotationCalculatorTest.php` |
| Strong | Feature | `tests/Feature/Admin/AdminQuotationFlowTest.php` (create + persist totals, filters) |

**Gaps**

- Non-Umrah calculators or alternate quote types (if any) — verify against `app/Services/Calculator` / quotation actions.
- Extreme markup/discount/tax combinations, currency rounding, and **line-item** breakdown assertions — may need more cases than current flows.
- Performance of large quotations — not covered.

---

## 4. Bookings

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Feature | `tests/Feature/Booking/BookingEngineFlowTest.php` |
| Strong | Feature | `tests/Feature/Admin/AdminBookingSupplierCostAndMarginTest.php` |
| Moderate | Feature | `tests/Feature/Admin/AdminBookingPaymentHttpTest.php` (booking + payment HTTP) |
| Moderate | Feature | `tests/Feature/Api/BookingRevalidationGuardTest.php` |
| Moderate | Feature | `tests/Feature/Integration/BookingRevalidationGuardApiTest.php` |
| Moderate | Unit | `tests/Unit/Integration/BookingRevalidationGuardTest.php` |

**Gaps**

- Full lifecycle: cancel, amend, no-show, split bookings — confirm whether covered elsewhere; no dedicated suite names found.
- Supplier confirmation failures and rollback — integration-level scenarios may be thin.

---

## 5. Payments & refunds

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Unit | `tests/Unit/Payment/PaymentServiceTest.php` — deposits, idempotency, full pay, wallet top-up/settle, insufficient wallet, **refund row**, overdue wallet flags |
| Moderate | Feature | `tests/Feature/Admin/AdminBookingPaymentHttpTest.php` |

**Gaps**

- Real **payment gateway** webhooks and signature verification — typically need HTTP feature tests with mocked gateways.
- Chargebacks, multi-currency settlement, and reconciliation reports — not evident from class names alone.
- Refund **HTTP** flows (admin UI/API) beyond `PaymentService` unit tests — likely shallower.

---

## 6. Customer portal (B2C)

| Strength | Type | Test class |
|----------|------|------------|
| Moderate | Feature | `tests/Feature/Customer/CustomerPortalTest.php` |
| Moderate | Feature | `tests/Feature/Customer/CustomerBookingDocumentDownloadTest.php` |
| Moderate | Feature | `tests/Feature/Customer/CustomerBookingDocumentLinkingTest.php` |

**Gaps**

- Full customer journey: register → book → pay → view itinerary — partial at best.
- Customer-visible **PII** leakage across tenants — overlap with tenancy tests; still worth explicit portal-only cases.

---

## 7. Agency portal

| Strength | Type | Test class |
|----------|------|------------|
| Moderate | Feature | `tests/Feature/Agency/AgencyPortalAccessTest.php` |

**Gaps**

- Agency-specific CRUD (sub-users, branding, commissions) — **missing** unless covered inside generic admin tests (unlikely).

---

## 8. CRM

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Feature | `tests/Feature/Admin/InquiryCrmTest.php` |
| Strong | Feature | `tests/Feature/Admin/InquiryOperationsTest.php` (also listed under admin CRUD) |

**Gaps**

- Pipeline stages, assignments, SLAs, email sequences tied to CRM — verify against `docs/20-crm-sales-pipeline.md` vs tests.
- Reporting on conversion — not clearly covered.

---

## 9. Public frontend

| Strength | Type | Test class |
|----------|------|------------|
| Moderate | Feature | `tests/Feature/Frontend/PackageAndGroupFilterTest.php` |
| Moderate | Feature | `tests/Feature/Frontend/FrontendInquirySubmissionTest.php` |
| Shallow | Feature | `tests/Feature/Frontend/PublicPackageSyncTest.php` |
| Shallow | Feature | `tests/Feature/Frontend/PublicGroupSyncTest.php` |
| Shallow | Feature | `tests/Feature/Frontend/LegacyRouteCompatibilityTest.php` |

**Gaps**

- Visual/regression (screenshots), accessibility, and SEO output — not automated here.
- High-traffic caching behavior for public pages — not covered.

---

## 10. Integrations (suppliers, API contracts, orchestration)

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Unit | `tests/Unit/Integration/SupplierJsonHttpClientTest.php` |
| Strong | Unit | `tests/Unit/Integration/SupplierAuthTokenManagementTest.php` |
| Strong | Unit | `tests/Unit/Integration/SupplierIntegrationBindingsTest.php` |
| Moderate | Unit | `tests/Unit/Integration/SupplierTokenPersistenceTest.php` |
| Moderate | Unit | `tests/Unit/Integration/ProviderCredentialResolverTest.php` |
| Moderate | Unit | `tests/Unit/Integration/NormalizedPayloadMetadataTest.php` |
| Moderate | Unit | `tests/Unit/Integration/FakeProviderPayloadMapperTest.php` |
| Moderate | Unit | `tests/Unit/Integration/FlightOfferComparisonEngineTest.php` |
| Moderate | Unit | `tests/Unit/Integration/IntegrationOrchestrationServiceTest.php` |
| Moderate | Unit | `tests/Unit/Integration/BookingRevalidationGuardTest.php` |
| Moderate | Unit | `tests/Unit/Integrations/ProviderResolverTest.php` |
| Moderate | Unit | `tests/Unit/Integrations/FakeProviderPayloadMapperTest.php` |
| Moderate | Unit | `tests/Unit/Integrations/FlightOfferComparisonEngineTest.php` |
| Moderate | Unit | `tests/Unit/Integrations/IntegrationOrchestrationServiceTest.php` |
| Strong | Feature | `tests/Feature/Api/IntegrationsEndpointsTest.php` |
| Strong | Feature | `tests/Feature/Api/IntegrationSearchSnapshotTest.php` |
| Moderate | Feature | `tests/Feature/Api/BookingRevalidationGuardTest.php` |
| Moderate | Feature | `tests/Feature/Integration/BookingRevalidationGuardApiTest.php` |
| Moderate | Feature | `tests/Feature/Integration/SupplierOrchestrationQualityTest.php` |
| Moderate | Feature | `tests/Feature/Integration/IntegrationObservabilityTest.php` |
| Moderate | Feature | `tests/Feature/Api/TenantProviderAuthorizationTest.php` |

**Maintenance note:** Duplicate class names exist under `tests/Unit/Integration/` and `tests/Unit/Integrations/` for orchestration, mappers, and flight comparison—keep both in sync or consolidate to avoid drift.

**Gaps**

- **Live** supplier sandboxes (Amadeus/Sabre/Travelport) — intentionally not part of default SQLite suite; use manual checklists (`docs/providers/*`).
- Per-vendor JSON golden-file tests — coverage depends on how exhaustive fakes are; treat as **risk** until documented per vendor.

---

## 11. Tenancy & data isolation

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Feature | `tests/Feature/Tenancy/TenantIsolationPhase2Test.php` |
| Moderate | Feature | `tests/Feature/Api/TenantProviderAuthorizationTest.php` |
| Moderate | Feature | `tests/Feature/Admin/TenantProviderAccessMatrixTest.php` |

**Gaps**

- Cross-tenant **enumeration** via IDs (booking, document, payment UUIDs) — add explicit negative tests if not already inside isolation suite.
- Super-admin “view all” vs tenant admin — matrix tests help; data-layer scoping still worth auditing.

---

## 12. Support / automation / communication

| Strength | Type | Test class |
|----------|------|------------|
| Shallow | Feature | `tests/Feature/Auth/PasswordResetTest.php` (notification fake) |
| Shallow | Feature | `tests/Feature/Auth/EmailVerificationTest.php` |
| Indirect | Feature | `tests/Feature/Admin/AdminBackgroundProcessingTest.php` (queues) |

**Gaps**

- Ticketing/support desk (`docs/25-support-desk.md`), automation rules (`docs/23-automation-foundation.md`), communication hub (`docs/24-communication-hub.md`) — **no** dedicated test classes found by name/path.
- Outbound mail/SMS/WhatsApp providers — **missing** automated contract tests.

---

## 13. CMS / SEO

| Strength | Type | Test class |
|----------|------|------------|
| Shallow | Feature | `tests/Feature/Admin/CmsSeoAccessTest.php` |

**Gaps**

- Content publish workflow, redirects, sitemap, meta rendering — **missing** or only manual.
- Public page SEO assertions — not covered.

---

## 14. Documents

| Strength | Type | Test class |
|----------|------|------------|
| Strong | Feature | `tests/Feature/Admin/AdminBookingDocumentUploadTest.php` |
| Strong | Feature | `tests/Feature/Admin/AdminBookingDocumentDownloadTest.php` |
| Strong | Feature | `tests/Feature/Admin/AdminBookingDocumentValidationTest.php` |
| Strong | Feature | `tests/Feature/Admin/AdminBookingDocumentLifecycleHardeningTest.php` |
| Moderate | Feature | `tests/Feature/Customer/CustomerBookingDocumentDownloadTest.php` |
| Moderate | Feature | `tests/Feature/Customer/CustomerBookingDocumentLinkingTest.php` |
| Indirect | Feature | `tests/Feature/Admin/AdminBackgroundProcessingTest.php` (virus scan job queued) |

**Gaps**

- Malware scan **outcomes** (infected file quarantine) — job dispatch tested; full pipeline may need more cases.
- Document retention / legal hold — not evident.

---

## 15. General API surface (non-integration-specific)

| Strength | Type | Test class |
|----------|------|------------|
| Moderate | Feature | `tests/Feature/Api/V1EndpointsTest.php` |

**Gaps**

- Exhaustive contract tests for every `v1` route — unlikely; treat as **shallow** until enumerated.

---

## 16. Scaffolding / placeholders

| Type | Test class |
|------|------------|
| Placeholder | `tests/Feature/ExampleTest.php` |
| Placeholder | `tests/Unit/ExampleTest.php` |

These do not materially reduce domain risk; safe to ignore for coverage planning or replace with real smoke tests.

---

## Red Flag 4 checklist alignment

| Question | Where confidence is built | Where gaps remain |
|----------|---------------------------|-------------------|
| Quotation math | `UmrahQuotationCalculatorTest`, `AdminQuotationFlowTest` | Edge cases, alternate products |
| Admin safe operation | Broad admin CRUD + `AdminPermissionMatrixTest` | Untested admin modules |
| Agency/customer see only own data | `TenantIsolationPhase2Test`, portal/document tests, agency access test | Enumeration, super-admin edge cases |
| Bookings/payments/refunds/documents/CRM | Booking flow tests, `PaymentServiceTest`, document suite, inquiry/CRM tests | Gateway webhooks, full booking lifecycle, support/automation |
| Integration contracts & orchestration | Many Unit + Feature integration tests; observability test | Live vendors, duplicate unit namespaces |
| Trust pre-launch regression | Full PHPUnit run on healthy SQLite harness | No CI workflow in-repo; support/CMS/communication mostly untested |

---

## Related docs

- `docs/30-sqlite-test-harness-map.md` — schema and suite dependencies
- `docs/13-testing-stabilization.md` — stabilization context
- `docs/05-api-contract.md` — API expectations vs tests

---

*Last updated: Red Flag 4.1 — coverage map creation (inventory of `tests/` tree).*
