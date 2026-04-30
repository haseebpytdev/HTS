# Enterprise Settings Architecture (Phase EA-2)

## Goal

Unify runtime settings into one enterprise architecture so platform controls are consistent, auditable, and safe across Super Admin and tenant operations.

## Core design

Settings are centralized in `application_settings` with:

- **Strong key names:** dot-notated keys like `integrations.default_provider`, `pricing.markup_mode`.
- **Category grouping:** first-class `category` column + key prefix semantics.
- **Typed values:** `value_type` tracks `string`, `bool`, `int`, `float`, `json`.
- **Scope-aware storage:** one table supports platform, tenant, module/provider, and optional user preference scopes.

## Storage model

`application_settings` columns (enterprise shape):

- `scope` (`platform|tenant|module_provider|user`)
- `scope_id` (nullable FK-like identifier for tenant/user scope)
- `provider` (nullable provider code for module/provider scope)
- `module` (nullable module/service code for module/provider scope)
- `category` (normalized category bucket)
- `key` (strongly named setting key)
- `value` (string-serialized payload)
- `value_type` (`string|bool|int|float|json`)
- timestamps

Uniqueness boundary:

- Unique tuple on `(scope, scope_id, provider, module, category, key)`.

This allows the same key to exist safely per scope without collisions.

## Required scopes

- **Platform:** global defaults and governance.
- **Tenant:** tenant/agency-specific overrides.
- **Module/provider:** per integration/provider + module behavior controls.
- **User preference (optional):** only for non-critical preference-level settings.

## Required categories

- `branding`
- `localization`
- `booking`
- `pricing`
- `tax`
- `payment`
- `integrations`
- `notifications`
- `approvals`
- `documents/security`
- `SEO/CMS`
- `tenancy`
- `analytics/reporting`

Storage canonicalization (implemented in `ApplicationSetting::canonicalCategory()`):

| UI/business label | Canonical stored category |
|---|---|
| `payment` / `payments` | `payment` |
| `documents/security` | `documents_security` |
| `SEO/CMS` | `seo_cms` |
| `analytics/reporting` | `analytics_reporting` |

All categories are normalized to lowercase underscore format for durable querying and unique-key consistency.

## Access pattern (service-first)

Runtime reads/writes go through `App\Services\System\SystemSettingsService`:

- Typed reads: `getString`, `getBool`, `getInt`, `getFloat`, `getArray`
- Scoped writes: `set`, `setMany`
- Context support: `scope`, `scope_id`, `provider`, `module`, `category`
- Safe fallback support: optional config fallback key for non-DB-ready/bootstrap paths

Controllers and runtime services should not directly query `ApplicationSetting` for business decisions. They should use `SystemSettingsService`.

Context normalization (implemented):

- `scope` is normalized through `ApplicationSetting::canonicalScope()` to one of:
  - `platform`
  - `tenant`
  - `module_provider`
  - `user`
- `category` is normalized through `ApplicationSetting::canonicalCategory()` before query/write.
- `value_type` is normalized through `ApplicationSetting::canonicalValueType()` before persistence.

## Resolution order

Recommended resolution order for runtime:

1. Most specific scope match (for example `module_provider` when applicable)
2. Tenant scope
3. Platform scope
4. Config fallback (`config/*` and env-backed values) when no DB value exists

Current implementation enforces typed retrieval with explicit context and fallback support; multi-level hierarchical resolution can be expanded incrementally in service methods without changing controller call sites.

## Backward compatibility and migration strategy

- Existing single-key rows are migrated to `scope=platform`.
- `category` is inferred from key prefix (`foo.bar` => category `foo`).
- `value_type` is inferred heuristically during migration and can be corrected on subsequent writes.
- Config files remain fallback sources only; authoritative runtime control moves into settings storage.

## Runtime integration points updated in this phase

- `IntegrationOrchestrationService` default provider resolution now uses `SystemSettingsService`.
- `ModuleRuntimeConfigService` pricing/tax/localization fallback reads now use `SystemSettingsService`.
- `TenancySettings` and admin tenancy/settings updates are wired to `SystemSettingsService`.

## Guardrails

- Keep secrets and sensitive credentials backend-restricted (env/config/secure secret paths), not editable as plain settings values.
- Keep supplier-specific parsing/mapping/auth internals in integration adapter layers; settings only toggle normalized behavior and policy.
- Apply Form Request validation for settings mutations in admin flows.

## Definition of done mapping

- One unified `application_settings` architecture now exists for platform/tenant/module-provider scopes.
- Typed access is standardized behind `SystemSettingsService`.
- Runtime-critical reads are service-based with safe config fallback behavior where needed.
- Architecture is documented for enterprise Super Admin implementation.
