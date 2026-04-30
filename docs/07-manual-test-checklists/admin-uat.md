# Admin UAT checklist

Purpose: validate operator-heavy workflows and visual/print quality not fully covered by automation.

## Environment + setup

- [ ] Log in as admin with realistic staging data (bookings, quotations, inquiries, tickets).
- [ ] Verify timezone/currency are set correctly in settings before running checks.
- [ ] Use both desktop and mobile viewport at least once for each critical module.

## Navigation consistency

- [ ] Open dashboard, quotations, bookings, payments, CRM, support, settings from sidebar.
- [ ] Confirm active menu highlight and breadcrumbs are correct on each page.
- [ ] Use browser back/forward in 3-4 flows; ensure no broken state or accidental resubmits.
- [ ] Confirm route guards for unauthorized pages show expected forbidden/redirect behavior.

## Quotation + booking operator flow

- [ ] Create quotation with adults/children, extras, markup, discount, promo.
- [ ] Verify line items, subtotal/discount/total values in UI are readable and consistent.
- [ ] Convert quotation to booking; confirm booking detail reflects quote totals.
- [ ] Run hold -> confirm -> amend -> cancel flow; verify status timeline is understandable.
- [ ] Capture supplier cost; visually verify margin-related values shown are coherent.

## Payments + refunds workflow

- [ ] Record deposit, balance payment, and full payment from booking page.
- [ ] Top-up agency wallet and settle booking from wallet; confirm success messages and balances.
- [ ] Attempt refund without approval request: verify blocked behavior and clear message.
- [ ] Approve refund and complete refund flow; confirm post-action redirect and audit visibility.

## Document lifecycle + audit

- [ ] Upload passport/ticket/payment proof files (pdf + image).
- [ ] Check scan state indicators: pending -> clean/infected/failed display wording is clear.
- [ ] Upload second version of same document type; verify latest vs previous version is obvious.
- [ ] Download and archive document; ensure action feedback is explicit.
- [ ] Confirm audit list includes uploader/downloader/validator/archive actor and timestamp.

## PDF + print quality (invoice/voucher)

- [ ] Open booking voucher and invoice for at least 3 bookings with different traveler counts.
- [ ] Print preview in Chrome/Edge:
  - [ ] no clipped headers/footers
  - [ ] table columns align
  - [ ] long names wrap cleanly
  - [ ] totals remain visible on A4
- [ ] Export/save PDF and review visual clarity (font, spacing, logo, page breaks).

## CRM + support operational usability

- [ ] Create inquiry, change status/pipeline, add follow-up notes.
- [ ] Convert inquiry to booking intent and confirm activity/history context remains readable.
- [ ] Create support ticket, add public/internal reply, escalate, resolve.
- [ ] Check long-form text entry/editing usability (copy/paste, scroll, save feedback).

## CMS/content checks

- [ ] Open landing pages/blog/promo admin modules and verify list + edit layout consistency.
- [ ] Review 2-3 long content entries for spacing, heading hierarchy, and link rendering.
- [ ] Confirm slug/SEO-related fields are visible and understandable to non-technical operators.

## Responsiveness quick pass

- [ ] At ~375px width, verify critical admin pages remain usable (no impossible controls).
- [ ] Confirm tables/cards do not obscure key action buttons (save/confirm/download).

## Exit criteria

- [ ] No blocker in booking, payment, refund, document, or support flows.
- [ ] PDF/print artifacts are acceptable for customer-facing use.
- [ ] Any issues are logged with screen, role, record id, and reproduction steps.
