# Compliance & Safety (Phase 17.1-17.4)

## Goal

Add a dedicated compliance and operational safety layer with auditability, approval controls, API monitoring, and backup run tooling.

## 17.1 Audit logs

- Added table/model:
  - `compliance_audit_logs`
  - `App\Models\ComplianceAuditLog`
- Added service:
  - `App\Services\Compliance\ComplianceAuditService`
- Added middleware:
  - `App\Http\Middleware\LogComplianceAuditTrail`
  - alias `compliance.audit` in `bootstrap/app.php`
- Middleware applied to:
  - admin route group
  - `/api/v1/integrations/*` route group

## 17.2 Approval workflows

- Added table/model:
  - `approval_requests`
  - `App\Models\ApprovalRequest`
- Added service:
  - `App\Services\Compliance\ApprovalWorkflowService`
- Added admin endpoints:
  - submit approval request
  - approve/reject review
- Added dashboard queue section for pending approvals.
- Added policy-driven approval gates via middleware:
  - `approval.gate:payment_refund,payment` on refund endpoint
  - `approval.gate:tenancy_toggle` on tenancy setting update
- Gate behavior:
  - requires an approved request matching `request_type` (and reference when provided)
  - auto-consumes approval after use
  - expires approvals after `COMPLIANCE_APPROVAL_TTL_HOURS` (default 72)

## 17.3 API monitoring

- Added service:
  - `App\Services\Compliance\ApiMonitoringService`
- Provides 24h monitoring summary:
  - total/success/failure/error-rate
  - provider-level breakdown from `integration_logs`
- Exposed on admin Compliance dashboard.

## 17.4 Backup tools

- Added table/model:
  - `backup_runs`
  - `App\Models\BackupRun`
- Added artisan command:
  - `safety:backup:run --disk=local --type=database`
- Added scheduler:
  - daily backup run at `03:00` with overlap protection
- Command records run status/path/size/error into `backup_runs`.
- Current backup artifact is a placeholder marker file; wire actual DB/file archive tooling per environment.
