# Enterprise Super Admin Readiness

## Phase EA-1 — Define the Super Admin control model

### Goal

Make it explicit what the Super Admin controls, what tenant admins control, and what remains backend-only in the current Laravel system.

### Control matrix purpose

This control-plane matrix defines ownership and management mode for enterprise settings:
- **Owner:** Platform Super Admin, Tenant Admin, or Backend/Ops only.
- **UI-managed:** Whether the control should be editable in admin UI.
- **Restricted:** Whether the control must remain backend-only for safety/compliance.

## Scope and control levels

- **Platform-wide (Super Admin):** Global defaults, governance, and cross-tenant controls.
- **Tenant-specific (Tenant Admin):** Agency/tenant operational controls within allowed platform policy.
- **Provider/module (Super Admin + limited Tenant Admin):** Integration and module behavior under provider-safe boundaries.
- **Backend-only restricted (Engineering/Ops):** Sensitive or structural controls not exposed in admin UI.

---

## 1) Platform-wide settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Platform maintenance mode policy | Super Admin | Yes | Expose global maintenance toggles/messages; enforce through middleware/runtime checks. |
| Global default currency/locale/timezone | Super Admin | Yes | Persist in settings store; tenant values may override only where allowed. |
| Global feature flags baseline | Super Admin | Yes | Use central settings + permission checks; tenant can only see approved toggles. |
| Global email/SMS sender identities (non-secret metadata) | Super Admin | Yes | Names/from-address labels UI-managed; secrets stay backend-only. |
| Global branding policy template | Super Admin | Yes | Defines allowed branding fields tenants can customize. |
| Global API rate limits / quotas policy | Super Admin | Partial | High-level policy in UI; hard throttling internals remain backend-only. |

## 2) Tenant-specific settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Agency profile and contact metadata | Tenant Admin | Yes | Standard CRUD with dedicated Form Requests. |
| Tenant branding assets (logo/colors within policy) | Tenant Admin | Yes | Must obey platform branding policy guardrails. |
| Tenant operational preferences (working hours, SLA windows) | Tenant Admin | Yes | Persist in tenant-scoped settings namespace. |
| Tenant user/role assignments (within platform role model) | Tenant Admin | Yes | Cannot create platform roles; only assign permitted tenant roles. |
| Tenant-specific notification recipients/templates | Tenant Admin | Yes | Template variables constrained to approved placeholders. |
| Tenant-level overrides for allowed defaults | Tenant Admin | Yes | Only keys explicitly marked overridable by platform. |

## 3) Provider/module settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Default active integration provider (`stub`/`travelport`/`sabre`/`amadeus`) | Super Admin | Yes | Managed in settings; runtime reads DB setting with config fallback. |
| Provider enable/disable by module/tenant | Super Admin | Yes | Respect permission and connection health checks before enabling. |
| Supported operations per provider/module (search/pricing/booking) | Super Admin | Yes | UI can toggle only normalized operation flags, not vendor payload logic. |
| Tenant authorization to consume provider modules | Super Admin | Yes | Managed centrally; tenant cannot self-authorize restricted providers. |
| Provider credentials and base URLs | Super Admin / Ops | Partial | Credential metadata in UI; secrets/endpoints resolved via config/env/credential resolver. |
| Supplier payload mapping/parsing logic | Backend only | No | Must stay in `App\Integrations\{Supplier}\*` adapters/mappers; never UI-editable. |

## 4) Finance settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Global pricing policy defaults (markup mode/value) | Super Admin | Yes | Stored as platform defaults; tenant override only if allowed. |
| Tax defaults and fallback percentages | Super Admin | Yes | Runtime fallback when module rule is absent. |
| Tenant wallet limits/payment terms (where enabled) | Super Admin / Tenant Admin | Partial | Super Admin sets policy bounds; tenant can edit within bounds. |
| Payment method availability per tenant | Super Admin | Yes | Controlled by gateway/module readiness and compliance flags. |
| Settlement/ledger balancing rules | Backend only | No | Implemented in services; not editable via UI to avoid accounting drift. |
| Gateway secret keys/webhook signing keys | Backend only | No | Config/env and secret vault paths only. |

## 5) Content settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Platform content templates (email/SMS/doc scaffolds) | Super Admin | Yes | Published as baseline templates. |
| Tenant content instances (landing copy, footer, notices) | Tenant Admin | Yes | Tenant can manage scoped content entries only. |
| CMS publishing workflows | Super Admin | Yes | Approval states can be enforced through policy + permission gates. |
| SEO defaults (global metadata rules) | Super Admin | Yes | Tenant may override only allowed fields. |
| Legal/compliance static text master | Super Admin | Yes | Tenant can reference but not alter protected legal masters. |
| Raw HTML/script injection policy | Backend only | No | Sanitization rules and CSP remain backend-controlled. |

## 6) Security/approval settings

| Control area | Owner | UI-managed | Notes for current Laravel system |
|---|---|---|---|
| Role-policy matrix and permission presets | Super Admin | Yes | UI manages assignment/presets; core role enum constraints stay code-backed. |
| Approval chains (quote/booking/payment actions) | Super Admin | Yes | Configure approver roles and thresholds; audit events required. |
| Session timeout and max session age policy | Super Admin | Partial | Policy values in UI; enforcement middleware remains backend implementation. |
| MFA/step-up requirements by role/action | Super Admin | Yes | Toggle policy-level requirements; auth internals remain backend-driven. |
| IP allow/block lists (tenant-level) | Super Admin / Tenant Admin | Partial | Tenant can suggest/add entries; platform decides final enforcement scope. |
| Security event retention policy | Super Admin | Yes | UI sets retention windows; storage/archival mechanics are backend-only. |

## 7) Backend-only restricted settings

These controls must remain restricted to backend code, environment config, or secure operations processes:

- Supplier auth flows, token parsing, retry semantics, and vendor-specific JSON mappers in `App\Integrations\{Supplier}\`.
- Secret material: API keys, private credentials, webhook signing secrets, encryption keys.
- Container bindings for integration contracts and low-level HTTP client behavior.
- Database schema/migration structure and table-level constraints.
- Audit raw payload persistence mechanics (request/response archives, correlation IDs, latency/status capture internals).
- Queue driver internals, cache lock strategy for token refresh, and concurrency controls.
- Error normalization internals (`ApiErrorData`, `SupplierIntegrationException`) and exception mapping pipeline.
- CSRF/session storage mechanics and middleware internals enforcing auth/session boundaries.

## UI-managed vs restricted implementation notes

- Use a settings namespace model (`platform.*`, `tenant.*`, `integrations.*`, `finance.*`, `security.*`, `content.*`) to keep ownership boundaries clear.
- Every mutable setting exposed in UI should include: owner, scope (platform/tenant/module), validation rule, and audit log entry.
- Keep provider-switch behavior configuration-driven: changing provider must not require controller/UI/DB rewrites.
- Restrict UI to policy/state toggles and normalized fields; vendor wire-format handling never leaves integration adapters/mappers.
- Apply dedicated Form Request classes per create/update flow and keep controllers orchestration-only.

## Minimum acceptance checks for this phase

- Super Admin ownership boundaries are explicit and non-overlapping.
- Tenant admin capabilities are constrained by platform policies.
- Provider/module controls are configuration-first and DTO/contract-safe.
- Sensitive controls are explicitly marked backend-only and excluded from UI scope.
