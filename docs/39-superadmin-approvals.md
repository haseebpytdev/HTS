# Super Admin Approvals (Phase EA-6)

## Goal

High-risk control panel actions must be visible, reviewable, and approval-gated with explicit lifecycle rules.

## Covered high-risk actions

- `payment_refund`
- `tenancy_toggle`
- `provider_production_activation`
- `provider_production_credential_replace`
- `tenant_plan_change`
- `manual_ledger_adjustment`
- `booking_force_cancel`
- `dangerous_setting_change`
- `document_security_override`

## Workflow model

`approval_requests` now supports:

- queue state: `pending`
- reviewed states: `approved`, `rejected`
- one-time use state: `consumed`
- reviewer note capture (`review_note`)
- object references (`reference_type`, `reference_id`, optional `reference_url`)
- TTL/expiry (`expires_at`)
- one-time consumption audit (`consumed_at`, `consumed_by_user_id`)
- optional custom expiry at submission time (must be future datetime)

## UI behavior

Super Admin compliance dashboard provides:

- **Pending approvals list** with action type, reference, TTL, reason, and submitted timestamp
- **Approve / reject controls** with notes
- **Approval history** with reviewed status, notes, references, and consumption timestamp

## Enforcement

Approval gate enforcement is centralized in `EnsureApprovalGate`:

- requires latest approved request for matching request type/reference
- rejects expired approvals (review TTL + explicit `expires_at`)
- consumes approval immediately on successful gate pass (single-use)
- provider activation/credential gate is enforced only for **production-risk variants** (sandbox/non-production changes bypass gate)

## Route-level coverage

High-risk control-panel routes are approval-gated through `approval.gate` middleware:

- `payment_refund` -> `admin.payments.refund`
- `tenancy_toggle` -> `admin.system.tenancy.update`
- `provider_production_activation` -> `admin.integrations.toggle`, `admin.modules.status.update`
- `provider_production_credential_replace` -> `admin.modules.credentials.update`
- `tenant_plan_change` -> `admin.tenants.plans.update`
- `manual_ledger_adjustment` -> `admin.agencies.wallet.top-up`
- `booking_force_cancel` -> `admin.bookings.cancel`
- `dangerous_setting_change` -> `admin.finance.settings.update`
- `document_security_override` -> `admin.documents.security-settings.update`

## Integration guidance

For any new high-risk action:

1. Add the action key to `ApprovalWorkflowService::supportedRequestTypes()`.
2. Gate the route with `approval.gate:<request_type>[,<route-model-param>]`.
3. Submit approval requests with an object reference and optional `reference_url`. Human-friendly `reference_type` aliases supported by UI include: `payment`, `booking`, `quotation`, `tenant`, `module`, `integration`, and `agency`.
4. Keep execution logic in services; do not implement approval logic in Blade.
