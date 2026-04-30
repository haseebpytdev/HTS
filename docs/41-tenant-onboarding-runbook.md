# Tenant Onboarding Runbook (Phase EA-12)

## Purpose

Provide a repeatable onboarding flow so each tenant is activated with correct governance, provider scope, approvals, and support readiness.

## Entry criteria

- commercial plan confirmed
- legal/billing profile approved
- named tenant owner and support owner assigned
- onboarding ticket created

## Onboarding flow

### Step 1 - Tenant profile setup

- Create or verify tenant profile.
- Confirm tenant code, commercial tier, and owner contacts.
- Record onboarding reference in operations notes.

### Step 2 - Plan and governance baseline

- Assign plan tier in tenant governance.
- Apply default quotas and soft/hard limits.
- Enable overage alerting.
- Validate plan-aligned permissions for search/pricing/booking.

### Step 3 - Module enablement

- Enable only required modules.
- Confirm module-level operations are aligned with use case.
- Confirm disabled modules are intentionally blocked.

### Step 4 - Provider enablement

- Enable permitted providers only.
- Set provider priority, fallback, and multi-provider policy.
- Verify operation flags per provider.
- Confirm runtime order behaves as expected for tenant.

### Step 5 - Security and policy inheritance

- Validate tenant inherits platform-level security defaults.
- Check document-security behavior for tenant workflows.
- Confirm any tenant override is scoped and auditable.

### Step 6 - Smoke validation

- Control panel visibility for tenant-related admin pages.
- Quote -> pricing -> booking flow (as allowed by plan).
- Integration access denied/allowed behavior.
- Support path and escalation path verified.

### Step 7 - Handover and sign-off

- Publish onboarding summary to support + account team.
- Include known constraints and next review date.
- Move onboarding ticket to done with attached evidence.

## Exceptions policy

- No out-of-plan module/provider access without documented exception.
- Exceptions require owner, reason, expiry, and reviewer.
- Temporary exception must include rollback date.

## First-week hypercare

- Daily quota/limit checks.
- Daily provider health check for enabled providers.
- Review failed actions and support tickets for policy friction.
- Confirm no unauthorized capability expansion.

## Common failure patterns

- Provider enabled but module disabled.
- Booking permission disabled while pricing/search enabled unexpectedly.
- Quota too restrictive for launch traffic.
- Missing approval for high-risk plan/governance changes.

## Recovery procedure (misconfiguration)

1. Contain impact (disable risky operation/provider temporarily).
2. Restore last known-good tenant matrix.
3. Re-run smoke validation.
4. Record remediation in audit + support notes.

## Required evidence artifacts

- onboarding ticket ID
- final tenant plan snapshot
- module/provider matrix snapshot
- any approval IDs used
- executor + timestamp
- follow-up review date

