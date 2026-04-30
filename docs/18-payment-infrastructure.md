# Payment infrastructure

Service-layer payments for B2B bookings and agency wallets. **No payment business logic lives in Blade**; UIs and HTTP controllers should call `App\Services\Payment\PaymentService` and `App\Services\Payment\LedgerService` only.

## Schema

| Table | Role |
|-------|------|
| `agency_wallets` | One row per agency: running `balance`, optional `credit_limit` (max debt when balance is negative), `payment_terms_days`, overdue flags (`is_overdue`, `overdue_since`, `first_negative_balance_at`), `last_payment_at`. |
| `payments` | Logical payment: `flow_type` (deposit, full_payment, balance_due, wallet_top_up), `source` (external_gateway, agency_wallet, manual), `status`, amounts, optional `idempotency_key`. |
| `transactions` | Gateway attempts linked to `payments` (model: `PaymentGatewayTransaction`). |
| `refunds` | Refund rows tied to payments / gateway transactions. |
| `ledger_entries` | Append-only signed movements that explain wallet balance changes (`wallet_credit`, `wallet_debit`, etc.). |

Migration: `database/migrations/2026_04_17_120000_payment_infrastructure.php`.

## Services

- **`LedgerService`** — `ensureWallet`, `append` (updates wallet + writes ledger), `syncWalletDerivedFields` (overdue from negative balance + terms), `assertSufficientBalanceForDebit` / credit line checks.
- **`PaymentService`** — `recordDeposit`, `recordFullPayment`, `recordBalancePayment` (external gateway), `recordWalletTopUp`, `payBookingFromWallet` (internal wallet settlement), `refundExternalPayment`, `balanceDueForBooking` / `sumCompletedForBooking`.

## Gateway abstraction

- Contract: `App\Contracts\Payments\PaymentGatewayInterface`.
- Drivers: **`ManualPaymentGateway`** (default, succeeds for local/tests), **`StripePaymentGateway`** (HTTP to Stripe when `STRIPE_SECRET` is set; fails fast if unset).
- Config: `config/payments.php`, env `PAYMENT_GATEWAY_DRIVER` (`manual` \| `stripe`).

## Agency credit and overdue

- **Credit line:** `agency_wallets.credit_limit` — `null` means unlimited negative balance; `0` means wallet cannot go below zero.
- **Overdue:** When balance is negative and `payment_terms_days` &gt; 0, `syncWalletDerivedFields` compares `first_negative_balance_at` + terms to `now()` and sets `is_overdue` / `overdue_since`.

## Thin admin HTTP layer

Routes (all `auth` + admin roles; CSRF applies in browser):

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| POST | `admin/bookings/{booking}/payments/deposit` | `BookingPaymentController@storeDeposit` | Gateway deposit |
| POST | `admin/bookings/{booking}/payments/balance` | `BookingPaymentController@storeBalancePayment` | Gateway partial balance |
| POST | `admin/bookings/{booking}/payments/full` | `BookingPaymentController@storeFullPayment` | Gateway full remaining due |
| POST | `admin/bookings/{booking}/payments/wallet` | `BookingPaymentController@storeWalletPayment` | Settle from agency wallet |
| POST | `admin/agencies/{agency}/wallet/top-up` | `AgencyWalletPaymentController@storeTopUp` | Gateway wallet top-up |
| POST | `admin/payments/{payment}/refund` | `PaymentRefundController@store` | Gateway refund (external payments) |

Dedicated Form Requests validate input; controllers delegate to **`PaymentService`** and map **`PaymentProcessingException`** to flash errors. **`BookingPaymentPanelBuilder`** supplies read-only data for the booking show screen (totals, wallet snapshot, recent payments).

## Tests

`tests/Unit/Payment/PaymentServiceTest.php` covers deposits, idempotency, full pay-off, wallet top-up + settlement, credit limit rejection, gateway refund persistence, and overdue derivation.

`tests/Feature/Admin/AdminBookingPaymentHttpTest.php` covers auth, deposit POST, validation, agency wallet top-up, and refund redirect.
