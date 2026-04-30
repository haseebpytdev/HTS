# B2C customer portal

End-customer area separate from staff (`users`) and agencies: **`customers`** table, **`customer`** session guard, URLs under **`/customer/*`**.

## 6.1 Customer auth

- **Register:** `GET/POST /customer/register`
- **Login / logout:** `GET/POST /customer/login`, `POST /customer/logout`
- **Password reset:** `customer.password.request`, `customer.password.email`, `customer.password.reset`, `customer.password.store` (broker **`customers`**, table **`customer_password_reset_tokens`**)
- Middleware **`customer.guest`** redirects authenticated customers away from auth pages.

## 6.2 Saved travelers

- Table **`customer_saved_travelers`** (belongs to `customers`).
- CRUD: `customer.saved-travelers.*` — route model binding scopes rows to the logged-in customer.

## 6.3 Booking view

- **`bookings.customer_id`** links a booking to a customer (nullable).
- **Admin:** booking show has **“Customer portal (B2C)”** — `POST admin/bookings/{booking}/customer` with `AssignBookingCustomerRequest` (`customer_email` must exist on `customers`).
- Customer **`GET /customer/bookings`**, **`GET /customer/bookings/{booking}`** — only rows where `customer_id` matches; cross-access returns **403** via **`CustomerBookingAccess`**.

## 6.4 Payment UI

- **`CustomerBookingPaymentController`** delegates to **`PaymentService`** (deposit / balance / full gateway flows). **`recorded_by_user_id`** is left null for B2C.
- Validation: **`CustomerRecordBookingDepositRequest`** (and balance/full) enforce ownership + balance-due rules (same idea as admin).

## 6.5 Documents

- **`GET /customer/bookings/{booking}/voucher`** and **`invoice`** — reuse admin print views after access check; invoice uses **`BookingService::ensureInvoiceIssued`**.

## Public navigation

- Main nav (guest) includes **Customer login** → `customer.login`.

## Tests

`tests/Feature/Customer/CustomerPortalTest.php` — registration, booking isolation, pay + documents, saved travelers.
