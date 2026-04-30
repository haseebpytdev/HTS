# Frontend UAT checklist

Purpose: validate public-site UX, navigation consistency, responsiveness, and CMS content quality.

## Public navigation and route behavior

- [ ] Open home, packages list, groups list, inquiry entry points, and legacy URLs.
- [ ] Verify header/footer links are consistent and do not dead-end.
- [ ] Use back/forward browser navigation through filter/search journeys.

## Package/group discovery flow

- [ ] Apply filters (destination/date/price where available) and verify results update correctly.
- [ ] Reset filters and confirm baseline listing returns.
- [ ] Open package/group detail pages and verify key information hierarchy.

## Inquiry submission UX

- [ ] Submit a valid inquiry from package/group/public form.
- [ ] Validate required-field errors are clear and field-level.
- [ ] Submit with long message text and verify successful save + confirmation messaging.

## CMS and visual content checks

- [ ] Review published landing pages and blog pages.
- [ ] Confirm heading structure, paragraph spacing, and image rendering consistency.
- [ ] Check links/buttons in content blocks for correctness.
- [ ] Verify SEO-visible text elements (title/meta snippets where rendered) are sensible.

## PDF/print-related public artifacts (if exposed)

- [ ] Open any publicly reachable printable artifact and verify print preview layout.
- [ ] Confirm typography and spacing remain readable on A4.

## Mobile responsiveness

- [ ] Test at common breakpoints (~375px, ~768px, desktop).
- [ ] Confirm cards, filter controls, inquiry forms, and nav menus remain usable.
- [ ] Verify no horizontal overflow on key pages.

## Exit criteria

- [ ] Public journey (discover -> view -> inquire) works without confusing steps.
- [ ] CMS-managed content is visually acceptable and link-safe.
- [ ] Mobile and desktop experiences are both release-ready.
