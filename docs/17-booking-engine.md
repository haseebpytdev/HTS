# Booking engine (Phase 4)

End-to-end **internal** booking workflow on top of quotations, with lifecycle, audit history, printable voucher/invoice, and **placeholder hooks** into the supplier integration layer (GDS).

## Sub-phase map

| Step | Deliverable |
|------|-------------|
| **4.1** | Schema: extended `bookings`, `booking_items`, `travelers`, `booking_status_histories` |
| **4.2** | `BookingService`, `BookingLifecycleManager`, `BookingRepository` |
| **4.3** | Quotation → draft booking; **hold** (`on_hold`); **confirm** (`confirmed`) |
| **4.4** | `BookingSupplierIntegrationBridge` — logs + `supplier_*_hook_status`; replace with `BookingOrchestrator` / real adapters |
| **4.5** | Admin **voucher** + **invoice** Blade (print-friendly); invoice number on first open (`INV-{booking_number}`) |
| **4.6** | **Cancel** (reason + history); **amend** (notes + optional traveler sync + `amended` history) |

## Status model

`App\Enums\BookingStatus`: `pending` (legacy), `draft`, `on_hold`, `confirmed`, `cancelled`.

Allowed transitions are enforced in `BookingLifecycleManager` (e.g. confirm from `draft`, `pending`, or `on_hold`).

## HTTP / UI (admin)

| Route | Action |
|-------|--------|
| `GET admin/bookings` | List + filters |
| `GET admin/bookings/{booking}` | Detail, lifecycle forms, history |
| `POST admin/quotations/{quotation}/bookings` | Create **draft** from quotation |
| `POST admin/bookings/{booking}/hold` | Optional `hold_expires_at` |
| `POST admin/bookings/{booking}/confirm` | Confirm + run supplier placeholders |
| `POST admin/bookings/{booking}/cancel` | `reason` required |
| `PATCH admin/bookings/{booking}/amend` | `internal_notes`, `remarks`, `amend_reason`; travelers via validated array (API-style extension) |
| `GET admin/bookings/{booking}/voucher` | Traveler voucher |
| `GET admin/bookings/{booking}/invoice` | Invoice (issues number if missing) |

Quotation detail includes **Create booking**.

## Code map

| Layer | Path |
|-------|------|
| Service | `app/Services/Booking/BookingService.php` |
| Lifecycle | `app/Services/Booking/BookingLifecycleManager.php` |
| Supplier hooks | `app/Services/Booking/BookingSupplierIntegrationBridge.php` |
| Actions | `app/Actions/Booking/CreateBookingFromQuotationAction.php`, `AmendBookingAction.php` |
| Controller | `app/Http/Controllers/Admin/BookingController.php` |
| Requests | `FilterBookingRequest`, `HoldBookingRequest`, `CancelBookingRequest`, `AmendBookingRequest` |
| Models | `Booking`, `BookingItem`, `Traveler`, `BookingStatusHistory` |
| Migration | `database/migrations/2026_04_16_100000_booking_engine_schema.php` |
| Tests | `tests/Feature/Admin/BookingEngineFlowTest.php` |

## Deployment

Run migration:

```bash
php artisan migrate --path=database/migrations/2026_04_16_100000_booking_engine_schema.php --force
```

## Next steps (product)

- Wire `BookingSupplierIntegrationBridge` to real flight/hotel flows (normalized DTOs only; see `docs/12-json-handling-rules.md`).
- Add agency portal read-only booking views if required.
- Extend **amend** Blade with repeatable traveler fields or a dedicated traveler API.
