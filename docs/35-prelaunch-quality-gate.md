# Pre-launch quality gate (RF4.10)

## Purpose

Convert go-live from a subjective judgment into an explicit **GO / NO-GO** decision using required evidence.

This gate is operational: each item is either **Pass**, **Waived (approved)**, or **Fail**.

## Decision outcomes

- **GO**: all mandatory checks pass, or only approved non-blocking waivers remain.
- **NO-GO**: any blocking check fails, or evidence is incomplete.

## Blocking rules (hard stop)

Go-live is blocked immediately if any of the following is true:

- smoke suite fails
- release-critical regression suite fails
- critical auth/access isolation defect exists
- blocking payment/booking/invoice/document defect exists
- manual UAT sign-off is incomplete
- migration rehearsal is incomplete or failed

## Required evidence package

Attach/record before decision meeting:

1. automated run outputs (command + timestamp + pass/fail summary)
2. known-issue list with severity and waiver decision
3. completed UAT checklists
4. migration rehearsal notes/logs and approver sign-off
5. final GO/NO-GO decision record with owner names

---

## 1) Automated suite requirements

Use commands from `docs/34-test-suite-execution.md`.

### 1.1 Smoke suite (mandatory)

- Command: `composer test:smoke`
- Requirement: **100% pass**
- Allowed waivers: **none**

### 1.2 Release-critical regression suite (mandatory)

- Command: `composer test:regression-critical`
- Requirement: **100% pass** across release-critical domains in `docs/32-release-critical-test-matrix.md`
- Allowed waivers: only via formal waiver process (see section 5), and never for critical auth/isolation or money/document blockers.

### 1.3 Full suite (mandatory with controlled exceptions)

- Command: `composer test:full`
- Requirement: pass, **or** only known waived issues remain.
- If failures exist:
  - they must be documented,
  - severity-classified,
  - explicitly approved by release authority.

---

## 2) Critical defect exclusion criteria

The following categories must have **zero open blockers**:

- **Auth and data isolation**
  - admin vs agency separation
  - customer vs customer separation
  - agency vs agency separation
  - tenancy-sensitive access paths
- **Financial/operational critical**
  - booking lifecycle regressions
  - payment/refund/ledger correctness regressions
  - invoice/voucher generation or readability blockers
  - document upload/validation/download/archive blocking issues

If any such defect is open at decision time -> **NO-GO**.

---

## 3) Manual UAT sign-off (mandatory)

All checklist documents must be completed and signed:

- `docs/07-manual-test-checklists/admin-uat.md`
- `docs/07-manual-test-checklists/agency-uat.md`
- `docs/07-manual-test-checklists/customer-uat.md`
- `docs/07-manual-test-checklists/frontend-uat.md`
- `docs/07-manual-test-checklists/integrations-uat.md`

Minimum sign-off fields per checklist:

- environment (local/staging)
- tester name + date
- pass/fail summary
- blocker list (if any) with issue IDs

If any checklist is missing or has unresolved blocker -> **NO-GO**.

---

## 4) Migration rehearsal (mandatory)

Must be completed using migration runbook expectations in `docs/29-production-migration-procedure.md`.

Minimum required rehearsal evidence:

- target environment identified
- pre-check and drift check completed
- migration executed without destructive workaround
- post-migration validation completed
- rollback/contingency readiness confirmed
- DB approver sign-off recorded

Missing rehearsal evidence -> **NO-GO**.

---

## 5) Known-issue waiver handling

Waivers are allowed only for non-blocking issues.

Each waiver entry must include:

- issue ID/link
- clear user impact statement
- severity (`critical`, `high`, `medium`, `low`)
- why release can proceed
- mitigation and monitoring plan
- owner + target fix date
- approver name/date

### Non-waivable (cannot be waived)

- critical auth/access isolation defects
- blocking payment/booking/invoice/document defects
- migration rehearsal failure/missing evidence
- smoke suite failure
- release-critical regression failure

---

## 6) Go-live decision checklist (meeting-ready)

- [ ] `composer test:smoke` passed
- [ ] `composer test:regression-critical` passed
- [ ] `composer test:full` passed or only approved waivers remain
- [ ] no critical auth/data-isolation failures
- [ ] no blocking payment/booking/invoice/document failures
- [ ] manual UAT checklists all signed off
- [ ] migration rehearsal completed and approved
- [ ] waiver register reviewed and approved
- [ ] release owner records final decision: **GO** / **NO-GO**

---

## Decision record template

Use this template in release notes / ticket:

- Release version:
- Date/time:
- Decision: GO / NO-GO
- Smoke result:
- Regression-critical result:
- Full-suite result:
- UAT status:
- Migration rehearsal status:
- Open waivers (IDs):
- Approved by:
- Notes/conditions:
