# Documents UAT Checklist

Purpose: manual pre-launch validation for document upload, scan lifecycle, quarantine, rescan, versioning, and download safety.

## Preconditions

- Admin test user and customer test user exist.
- At least one booking exists and is visible in admin.
- Queue worker is running in the target environment (or queue monitor confirms jobs execute).
- Test files prepared:
  - valid PDF (`passport-valid.pdf`)
  - valid image (`traveler-photo.jpg`)
  - invalid script/executable-like file (`payload.php` / `payload.exe`)
  - large file above configured max size

## Admin flow checks

- [ ] Open booking document panel and confirm existing rows show:
  - scan status badge
  - scan engine
  - last scanned timestamp
  - scan/failure summary note
  - quarantine/archived indication where applicable
- [ ] Upload valid PDF; verify success message and new row appears.
- [ ] Upload valid image; verify success message and new row appears.
- [ ] Upload invalid script-like file; verify upload is blocked with validation error.
- [ ] Upload oversize file; verify upload is blocked with size error.
- [ ] For a new valid upload, verify initial scan state appears as pending then transitions after queue processing.
- [ ] For an infected/suspicious file scenario, verify document is marked quarantined and cannot be normally downloaded.
- [ ] For a failed scan scenario, verify file remains blocked and clearly shown as failed.
- [ ] Use Rescan button on failed/suspicious file; verify status returns to pending then updates to latest result after job completion.
- [ ] Attempt to approve document with non-clean scan state; verify approval is blocked.
- [ ] Approve clean document; verify approval succeeds.

## Versioning and archive checks

- [ ] Upload two files of same document type (e.g., passport v1 then v2); verify version increment and linkage.
- [ ] Archive the latest version; verify archive indicator appears and row is no longer active.
- [ ] Confirm archive action does not bypass scan-state protections.

## Customer-facing checks

- [ ] Login as linked customer and open booking page.
- [ ] Confirm only approved + customer-visible + clean docs are listed.
- [ ] Attempt direct URL download of infected/suspicious/failed doc (if URL known); verify blocked response.
- [ ] Confirm customer-facing messaging is simple and non-technical (no raw scanner internals/signatures exposed).

## Download experience checks

- [ ] Download clean approved PDF from admin and customer flows; verify filename and file integrity.
- [ ] Verify blocked downloads for unsafe files do not stream file content.
- [ ] Confirm large-file handling remains stable (no UI crash/timeouts; clear error where expected).

## Queue delay and recovery checks

- [ ] Pause queue worker briefly and upload file; verify pending scan state remains visible.
- [ ] Resume worker; verify state transitions correctly.
- [ ] Trigger temporary scanner failure mode in staging; verify status moves to failed and remains blocked until rescan.

## Audit verification (operator)

- [ ] Verify `uploaded` audit recorded on valid uploads.
- [ ] Verify `validated` audit recorded on approval/rejection changes.
- [ ] Verify `rescan_requested` audit recorded on rescan action.
- [ ] Verify `downloaded` audit recorded for successful downloads.
- [ ] Verify `download_blocked` audit recorded when unsafe downloads are attempted.
- [ ] Verify `archived` audit recorded on archive action.

## Exit criteria

- [ ] All critical checks above pass with no bypass of quarantine/download/approval restrictions.
- [ ] Any failed check is logged with booking ID, document ID, environment, and reproduction steps.
