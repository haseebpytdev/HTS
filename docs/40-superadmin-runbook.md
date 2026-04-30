# Super Admin Operations Runbook (Phase EA-12)

## Purpose

Run the Super Admin control panel as an enterprise operations console with clear ownership, repeatable procedures, and auditable outcomes.

## Audience

- Super Admin Lead
- Platform Operations Admin
- Support Operations Admin
- Compliance/Approval Reviewer

## Core operating model

### Shift handover standard

Each shift must hand over:

- open incidents (SEV, impact, owner, ETA)
- pending approvals older than SLA
- degraded providers/modules and active mitigations
- tenant onboarding/plan changes in progress
- document-security exceptions and quarantined trends

### Control panel systems of record

- **Operations Center:** KPI, action queue, recent activity
- **Compliance Dashboard:** approvals queue/history
- **Monitoring:** health overview, logs, async task board, failed alerts
- **Governance:** tenants, module/provider controls, finance controls, document security

## Daily run sequence (start-of-day)

1. Review `admin.operations-center` for backlog and red KPIs.
2. Review monitoring pages:
   - `admin.monitoring.health-overview`
   - `admin.monitoring.integration-logs`
   - `admin.monitoring.async-task-monitor`
   - `admin.monitoring.failed-jobs-alerts`
3. Review `admin.compliance.dashboard`:
   - pending approvals
   - expiring approvals
   - rejected actions requiring follow-up
4. Review tenancy governance:
   - plan breaches
   - quota pressure
   - provider/module mismatch by tenant
5. Review document-security policy/alerts:
   - scan failures, suspicious, infected
   - blocked downloads
   - auto-rescan queue behavior

## Approval workflow playbook

### Request quality standard

All requests must include:

- exact request type
- affected object reference and URL
- business reason
- risk statement
- rollback plan
- requested expiry (TTL) if non-default

### Reviewer checklist

- Is the request type correct for the risk?
- Is scope minimal and reversible?
- Is there rollback evidence?
- Does execution window avoid peak risk?

### Execution standard

- Execute only while approval is active.
- Never reuse consumed approvals.
- Record outcome in compliance/audit notes with timestamp and actor.

## Health monitoring playbook

### Priority signals

- provider test failures or error-rate spikes
- queue backlog growth / failed jobs burst
- scheduler stale signal
- notification failures
- document scan failures and quarantine growth
- support SLA breach growth

### Triage decision tree

1. Determine blast radius: platform, multi-tenant, tenant-only.
2. Check recent changes and approvals.
3. Apply immediate safe mitigation (disable risky provider/module path, reduce priority, force fallback).
4. Escalate to engineering with evidence:
   - correlation IDs
   - request IDs
   - timestamps
   - provider/tenant scope
5. Track status updates every 30-60 minutes until stabilized.

## Incident response basics

### Severity model

- **SEV-1:** platform outage or major security/compliance risk
- **SEV-2:** major degradation for many tenants
- **SEV-3:** single-tenant significant degradation
- **SEV-4:** minor non-critical defect

### First 30 minutes

1. Assign incident owner and severity.
2. Contain using control-panel-safe actions.
3. Start stakeholder updates.
4. Preserve evidence (logs, approval references, action timeline).

### Closeout

- confirm recovery smoke checks
- publish post-incident notes
- assign corrective actions and due dates

## Audit and evidence standard

For sensitive actions, capture:

- approver
- executor
- timestamp
- changed object/value
- reason
- linked ticket/incident/reference

## RACI (quick reference)

- **Approve high-risk changes:** Compliance Reviewer, Super Admin Lead
- **Execute approved changes:** Platform Ops Admin
- **Customer communication:** Support Admin + Account owner
- **Technical remediation:** Engineering on-call

## Escalation contacts template

- Platform Ops on-call: `<name/rotation>`
- Engineering on-call: `<name/rotation>`
- Compliance approver: `<name/rotation>`
- Customer communications owner: `<name/rotation>`

