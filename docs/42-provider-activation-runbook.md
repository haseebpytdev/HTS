# Provider Activation and Credential Rotation Runbook (Phase EA-12)

## Purpose

Standardize provider onboarding, production activation, and production credential replacement using Super Admin control-panel governance.

## Scope

- provider/module onboarding
- sandbox to production activation
- production credential replacement
- post-change validation and rollback

## Preconditions

- provider commercial and technical readiness confirmed
- sandbox module configuration completed
- sandbox connection test passed
- high-risk approvals prepared:
  - `provider_production_activation`
  - `provider_production_credential_replace`

## Provider onboarding (sandbox-first)

1. Create/verify module and provider metadata.
2. Set supported operations (`search`, `pricing`, `booking`) as applicable.
3. Configure:
   - environment = sandbox
   - priority
   - fallback and multi-provider policy
   - pricing/tax defaults
4. Configure credential source strategy.
5. Store sandbox credentials securely.
6. Run health test and confirm stable status.
7. Validate tenant assignment matrix.
8. Record onboarding readiness summary.

## Production activation procedure

### A. Approval preparation

Submit approval with:

- provider/module reference
- activation window
- risk statement
- rollback steps
- owner and communication plan

### B. Change execution

1. Confirm approval is active.
2. Switch provider/module to production environment.
3. Keep initial traffic controlled (priority/fallback strategy ready).
4. Run health test and controlled smoke transaction.

### C. Post-activation validation

- auth success rate normal
- response latency within baseline
- fallback behavior matches policy
- no tenant authorization regressions

## Production credential replacement procedure

1. Prepare new secret set in approved secure source.
2. Submit `provider_production_credential_replace` approval.
3. Execute within approved TTL:
   - update credentials through control panel
   - never log plaintext credentials
4. Run immediate connection test.
5. Observe logs for at least 30 minutes:
   - auth failures
   - timeout spikes
   - failover increase
6. If unstable:
   - roll back to last-known-good credentials (if retained path exists)
   - reduce/disable risky provider routing
   - escalate to engineering/provider contact

## Rollback playbook

- reduce provider priority or disable provider
- enforce fallback provider path if configured
- confirm service stabilization through monitoring
- publish incident/change advisory to support team

## Post-change monitoring cadence

- first hour: every 10-15 minutes
- first 24 hours: hourly checks
- first 7 days: daily trend review

## Evidence checklist

- approval IDs
- executor + reviewer names
- before/after config snapshot
- health test results
- monitoring observations
- rollback evidence (if used)

