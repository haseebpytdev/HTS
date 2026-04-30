# Control Panel Operations Checklist (Phase EA-12)

## Purpose

Single operational checklist for daily control-panel operations, approvals, monitoring, incidents, and go-live readiness.

## A) Daily startup checklist

- [ ] Review Operations Center KPIs and action queue.
- [ ] Review monitoring pages for new red signals.
- [ ] Review pending/expired approvals.
- [ ] Review tenant quota breaches and escalation backlog.
- [ ] Review provider health failures and API error trend.
- [ ] Review document scan/quarantine/download-blocked trend.

## B) Tenant onboarding checklist

- [ ] Tenant profile verified.
- [ ] Plan tier and quotas set.
- [ ] Module matrix configured.
- [ ] Provider matrix configured (priority/fallback/multi-provider).
- [ ] Tenant smoke tests completed.
- [ ] Support handover note published.

## C) Approval workflow checklist

- [ ] Correct high-risk request type used.
- [ ] Object reference + route URL included.
- [ ] Business reason and rollback steps documented.
- [ ] Reviewer note captured.
- [ ] Action executed only during active TTL.
- [ ] Completion evidence linked and approval consumed.

## D) Provider activation checklist

- [ ] Sandbox health check passed.
- [ ] Production activation approval granted.
- [ ] Environment switched with fallback strategy ready.
- [ ] Post-change health and smoke check passed.
- [ ] 30-minute monitoring window completed.

## E) Production credential replacement checklist

- [ ] Replacement approval granted (`provider_production_credential_replace`).
- [ ] Credentials updated without exposing plaintext.
- [ ] Immediate connection test passed.
- [ ] Auth/timeout/failover metrics observed for 30+ minutes.
- [ ] Rollback path prepared or executed (if required).

## F) Health monitoring checklist

- [ ] Integration error-rate and failed tests within threshold.
- [ ] Queue/failed jobs trend stable.
- [ ] Scheduler heartbeat fresh.
- [ ] Notification failure trend reviewed.
- [ ] Support SLA breaches assigned.
- [ ] Document security failures triaged.

## G) Incident response basics checklist

1. **Detect/declare**
   - [ ] SEV assigned.
   - [ ] Incident owner assigned.
2. **Contain**
   - [ ] Safe mitigation applied via control panel.
3. **Communicate**
   - [ ] Stakeholders updated with cadence and scope.
4. **Investigate**
   - [ ] Logs/correlation IDs/approval references collected.
5. **Recover**
   - [ ] Service smoke checks passed.
6. **Close**
   - [ ] Post-incident actions and owners recorded.

## H) Go-live operations checklist

### Platform

- [ ] Super Admin and reviewer roster complete.
- [ ] Approval gates verified on risky actions.
- [ ] Monitoring access confirmed for ops team.
- [ ] Document-security baseline reviewed and approved.

### Tenant readiness

- [ ] Tenant plans and quotas validated.
- [ ] Tenant module/provider matrices validated.
- [ ] Support ownership and escalation route confirmed.

### Provider readiness

- [ ] Provider health checks green.
- [ ] Runtime routing strategy validated.
- [ ] Credential source and rotation process validated.
- [ ] Production activation and evidence complete.

### Hypercare

- [ ] Daily review cadence defined.
- [ ] Weekly governance review scheduled.
- [ ] Escalation contacts published.

## I) Weekly governance checklist

- [ ] Review high-risk changes and approvals.
- [ ] Review tenant exceptions and upcoming expiries.
- [ ] Review provider reliability and fallback usage.
- [ ] Review document retention/quarantine outcomes.
- [ ] Review unresolved operational risks and owners.

