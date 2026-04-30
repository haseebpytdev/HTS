# Agency UAT checklist

Purpose: verify agency-side usability, data isolation, and day-to-day sales operations.

## Login + navigation

- [ ] Log in as agency user A and open dashboard, quotations, inquiries, profile.
- [ ] Confirm top-level navigation labels and active states are consistent.
- [ ] Use direct URL access attempts to admin routes; verify forbidden behavior.

## Data isolation (agency vs agency)

- [ ] Prepare agency A and B records (quotations/inquiries).
- [ ] As agency A user:
  - [ ] quotation list shows only agency A data
  - [ ] inquiry list shows only agency A data
  - [ ] direct URL to agency B quotation returns forbidden
- [ ] Repeat one check as agency B user for symmetry.

## Quotation workflow

- [ ] Open quotation detail; verify totals and line items are readable.
- [ ] Submit revision request with meaningful message.
- [ ] Submit booking intent and verify confirmation message.
- [ ] Attempt revision/booking intent on other-agency quotation and confirm block.

## Profile + long-form usability

- [ ] Update profile fields and save.
- [ ] Paste long text in profile/support fields; verify input behavior and save response.
- [ ] Reload and confirm values persisted correctly.

## Responsiveness

- [ ] Repeat quotation index/detail and inquiry index checks on mobile width (~375px).
- [ ] Ensure action buttons remain visible and scroll behavior is manageable.

## Exit criteria

- [ ] Agency user can complete core flow (view quote -> request revision/intent) without friction.
- [ ] Cross-agency isolation is confirmed by direct URL and list-level checks.
- [ ] No major navigation or responsive usability blockers remain.
