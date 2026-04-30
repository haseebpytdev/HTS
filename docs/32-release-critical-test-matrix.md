# Release-critical test matrix (Red Flag 4.2)

## Purpose

Separate **pre-launch gate** coverage from **important** and **secondary** coverage so release decisions are explicit: what **must** be green before go-live versus what is **nice to have**.

This matrix is **operational**: it names domains, ties them to representative automated tests where they exist, and states how the gate is run. It does not replace manual UAT or live-supplier checklists.

## How to run the gate

| Step | Command / action |
|------|------------------|
| Default automated gate | From `apnasafar-portal`: `php vendor/bin/phpunit` (runs **Unit** + **Feature** suites per `phpunit.xml`) |
| Focus a domain | `php vendor/bin/phpunit --filter <ClassName>` |
| Schema context | SQLite `:memory:` harness; see `docs/30-sqlite-test-harness-map.md` and `docs/31-test-coverage-map.md` |

**Interpretation:** If any test in a **Critical** row fails, treat the build as **not go-live ready** until fixed or risk-accepted in writing. **Important** failures warrant a scoped fix or deferral decision. **Secondary** failures are tracked but do not block by default.

---

## Tier 1 — Critical (must pass before go-live)

These domains match the business’s highest-risk flows: money, identity, isolation, core commerce, and integration contracts.

| Domain | Why it is gate-level | Example test classes (automated) |
|--------|----------------------|-----------------------------------|
| **Auth + roles** | Wrong access breaks every other control | `tests/Feature/Auth/AuthenticationTest.php`, `RoleAccessTest.php`, `PermissionSystemTest.php` |
| **Quotations + calculator** | Bad math → bad quotes, bookings, and revenue | `tests/Unit/Calculator/UmrahQuotationCalculatorTest.php`, `tests/Feature/Admin/AdminQuotationFlowTest.php` |
| **Bookings** | Core fulfillment and supplier hooks | `tests/Feature/Booking/BookingEngineFlowTest.php`, `tests/Feature/Admin/AdminBookingSupplierCostAndMarginTest.php` |
| **Payments + refunds + ledger** | Cash and liability correctness | `tests/Unit/Payment/PaymentServiceTest.php`, `tests/Feature/Admin/AdminBookingPaymentHttpTest.php` |
| **Customer access isolation** | B2C must not see others’ data | `tests/Feature/Customer/CustomerPortalTest.php`, `CustomerBookingDocumentDownloadTest.php`, `CustomerBookingDocumentLinkingTest.php` |
| **Agency access isolation** | B2B boundaries | `tests/Feature/Agency/AgencyPortalAccessTest.php` |
| **Public package / group flows** | Lead gen and catalog correctness | `tests/Feature/Frontend/PackageAndGroupFilterTest.php`, `FrontendInquirySubmissionTest.php`, `PublicPackageSyncTest.php`, `PublicGroupSyncTest.php` |
| **Inquiry + CRM flow** | Sales pipeline integrity | `tests/Feature/Admin/InquiryCrmTest.php`, `InquiryOperationsTest.php` |
| **Integration API contracts** | Predictable supplier/orchestration behavior | `tests/Feature/Api/IntegrationsEndpointsTest.php`, `IntegrationSearchSnapshotTest.php`, `tests/Feature/Api/TenantProviderAuthorizationTest.php`, `tests/Feature/Integration/IntegrationObservabilityTest.php`, `SupplierOrchestrationQualityTest.php` |
| **Document lifecycle** | Compliance and PII exposure risk | `tests/Feature/Admin/AdminBookingDocument*Test.php`, `tests/Feature/Customer/CustomerBookingDocument*Test.php` |
| **Tenancy guards** | Cross-tenant leakage | `tests/Feature/Tenancy/TenantIsolationPhase2Test.php`, `tests/Feature/Admin/TenantProviderAccessMatrixTest.php` |
| **Settings / approval-gated sensitive actions** | Misconfiguration or bypass of guarded ops | `tests/Feature/Admin/SettingsModuleTest.php`, `AdminPermissionMatrixTest.php`; extend with explicit approval workflows when present in routes |

**Note:** **Live** supplier calls (Amadeus/Sabre/Travelport) are **not** part of this PHPUnit gate; use `docs/providers/*` and controlled manual runs.

---

## Tier 2 — Important (run before release; fix or consciously defer)

High value for operations and regression confidence, but not every failure implies an immediate stop-ship if the gap is understood and mitigated.

| Domain | Example test classes |
|--------|----------------------|
| **Revalidation / booking guards (API + integration)** | `tests/Feature/Api/BookingRevalidationGuardTest.php`, `tests/Feature/Integration/BookingRevalidationGuardApiTest.php`, `tests/Unit/Integration/BookingRevalidationGuardTest.php` |
| **Integration unit depth (HTTP client, tokens, mappers)** | `tests/Unit/Integration/SupplierJsonHttpClientTest.php`, `SupplierAuthTokenManagementTest.php`, `ProviderCredentialResolverTest.php`, `tests/Unit/Integrations/*` |
| **Admin CRUD breadth (masters)** | `tests/Feature/Admin/HotelCrudTest.php`, `PackageCrudTest.php`, `GroupCrudTest.php`, `FlightEntryCrudTest.php`, visa/transport rate CRUD tests |
| **Exports / audit trail** | `tests/Feature/Admin/ExportHistoryRecordingTest.php` |
| **Analytics / reporting** | `tests/Feature/Admin/AdminAnalyticsReportsTest.php` |
| **Background jobs (queue dispatch)** | `tests/Feature/Admin/AdminBackgroundProcessingTest.php` |
| **Legacy URL compatibility** | `tests/Feature/Frontend/LegacyRouteCompatibilityTest.php` |
| **General API v1 smoke** | `tests/Feature/Api/V1EndpointsTest.php` |
| **CMS / SEO access** | `tests/Feature/Admin/CmsSeoAccessTest.php` |

---

## Tier 3 — Secondary (nice to have; informational)

Useful for hygiene and developer feedback; failures alone should not define go-live unless they expose a Critical-tier gap.

| Item | Notes |
|------|--------|
| **Placeholder / scaffold tests** | `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` |
| **Profile-only flows** | `tests/Feature/ProfileTest.php` |
| **Password confirmation / update** (without role matrix) | `PasswordConfirmationTest.php`, `PasswordUpdateTest.php` — still run; lower business risk than role matrix |
| **Deep SEO/content rendering** | Not meaningfully covered in PHPUnit; manual or dedicated tooling |
| **Support desk / automation / comms hub** | Few dedicated suites; see `docs/31-test-coverage-map.md` gaps |
| **Duplicate unit namespaces** | `tests/Unit/Integration/` vs `tests/Unit/Integrations/` — maintenance risk, not a user-facing domain |

---

## Mapping: “nice to have” vs “must pass”

| Must pass (Tier 1) | Nice to have (Tier 2–3) |
|--------------------|-------------------------|
| Authz for admin/agency/customer/API tenant boundaries | Extra CRUD permutations, export edge cases |
| Quote math + persistence + quote→booking money fields | Analytics CSV content, job payload internals |
| Booking lifecycle critical path | Every status transition combination |
| PaymentService + payment HTTP + refunds path | Gateway webhook signature suites (add when implemented) |
| Integration contract + tenant provider auth | Full vendor JSON golden files per supplier |
| Documents + tenancy isolation | CMS body HTML, blog SEO scoring |
| CRM + public lead paths | Legacy route exhaustive list |

---

## Release checklist (short)

1. `php vendor/bin/phpunit` — **all green** for Tier 1–2 suites you include in the release branch.
2. Spot-check **Tier 1** domains with the filter command if time-boxed.
3. Execute **manual** live-integration and payment-gateway checks per environment docs.
4. Record any **risk acceptance** for deferred Tier 1 gaps (name, owner, mitigation).

---

## Related docs

- `docs/31-test-coverage-map.md` — full inventory by domain  
- `docs/30-sqlite-test-harness-map.md` — SQLite schema dependencies  

---

*Red Flag 4.2 — release-critical test matrix.*
