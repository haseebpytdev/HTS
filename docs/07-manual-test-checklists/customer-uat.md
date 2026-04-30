# Customer UAT checklist

Purpose: validate customer portal experience, visual quality, and customer-only access boundaries.

## Auth and onboarding

- [ ] Register a new customer account.
- [ ] Log out and log in again with same account.
- [ ] Password reset flow works end-to-end (request + reset + login).

## Booking visibility isolation (customer vs customer)

- [ ] Prepare booking for customer A and booking for customer B.
- [ ] As customer A:
  - [ ] bookings list shows only customer A booking
  - [ ] direct URL to customer B booking returns forbidden
  - [ ] voucher/invoice access only for customer A booking

## Payment UX checks

- [ ] Perform deposit on own booking and confirm response messaging.
- [ ] Attempt balance/full payment on another customer booking and verify forbidden behavior.
- [ ] Confirm visible payment states/messages are understandable for non-technical users.

## Saved travelers workflow

- [ ] Create saved traveler.
- [ ] Edit saved traveler with realistic passport-related fields.
- [ ] Delete saved traveler and confirm removal.
- [ ] Verify no cross-customer access to traveler records.

## PDF/print quality (customer-facing)

- [ ] Open voucher and invoice from customer portal.
- [ ] Print preview check:
  - [ ] clear traveler details
  - [ ] totals and identifiers visible
  - [ ] no overlapping/clipped content
- [ ] Save PDF and validate readability on desktop and mobile PDF viewer.

## Mobile responsiveness

- [ ] Repeat bookings list/detail/payment actions on mobile width.
- [ ] Confirm forms are usable without hidden buttons or layout breaks.

## Exit criteria

- [ ] Customer can self-serve booking, payment, and document retrieval smoothly.
- [ ] Customer-to-customer isolation is verified.
- [ ] PDF and print output quality is acceptable for customer communication.
