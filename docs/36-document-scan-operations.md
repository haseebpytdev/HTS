# 36. Document Scan Operations

This document defines environment-safe scanner behavior and required operator configuration for document ingestion and scan lifecycle.

## Goals

- Keep local and test environments deterministic and easy to run.
- Ensure staging can validate real scanner integration.
- Enforce production fail-closed behavior for document safety.

## Configuration keys

All keys are read from `.env` via `config/documents.php`.

- `DOCUMENT_SCAN_MODE`: `disabled|stub|real`
- `DOCUMENT_SCAN_PROVIDER`: `stub|clamav`
- `DOCUMENT_SCAN_REQUIRE_REAL_IN_PROD`: `true|false`
- `DOCUMENT_SCAN_STUB_STATUS`: one of `clean|infected|suspicious|failed|skipped`
- `DOCUMENT_SCAN_STUB_MESSAGE`: safe message stored in scan result metadata
- `DOCUMENT_SCAN_MAX_FILE_SIZE_BYTES`: scanner payload cap
- `DOCUMENT_UPLOAD_MAX_FILE_SIZE_BYTES`: pre-scan upload max size
- `CLAMAV_HOST`, `CLAMAV_PORT`, `CLAMAV_TIMEOUT_SECONDS`: real scanner connection

## Recommended per environment

### Local development

- `DOCUMENT_SCAN_MODE=stub` (or `disabled` for offline workflows)
- `DOCUMENT_SCAN_PROVIDER=stub`
- deterministic status with `DOCUMENT_SCAN_STUB_STATUS`
- upload validation remains active (mime/ext/size/content checks)

### Local testing

- `DOCUMENT_SCAN_MODE=stub`
- `DOCUMENT_SCAN_PROVIDER=stub`
- keep fixed `DOCUMENT_SCAN_STUB_STATUS` per test scenario
- no real scanner dependency expected

### Staging

- `DOCUMENT_SCAN_MODE=real`
- `DOCUMENT_SCAN_PROVIDER=clamav`
- connect to staging ClamAV or scanner service
- verify timeout behavior and non-clean status handling before release

### Production

- `DOCUMENT_SCAN_MODE=real`
- `DOCUMENT_SCAN_PROVIDER=clamav`
- `DOCUMENT_SCAN_REQUIRE_REAL_IN_PROD=true`
- if scanner policy is violated, scan returns `failed` and file remains blocked

## Failure behavior

- `failed`, `infected`, `suspicious` are blocked states for normal download/approval flows.
- blocked download attempts are audited as `download_blocked`.
- admin can trigger `rescan_requested`; status moves back to `pending_scan`.
- only `clean` is considered safe for downstream usage.

## Operator runbook checks

- Confirm scanner connectivity (`CLAMAV_HOST:CLAMAV_PORT`) from app runtime.
- Confirm uploads enter `pending_scan` and transition out via queue job.
- Confirm blocked files show quarantine indicators in admin booking document panel.
- Confirm customer access only sees approved + clean documents.

## Notes

- This phase configures behavior only; infrastructure provisioning (scanner service deployment, network policy, container setup) is handled separately.
