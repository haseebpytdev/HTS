<?php

namespace App\Services\Payment;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\GatewayChargeRequest;
use App\Data\Payments\GatewayRefundRequest;
use App\Enums\GatewayTransactionStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentSource;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGatewayTransaction;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\Exceptions\InvalidPaymentAmountException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function sumCompletedForBooking(Booking $booking): string
    {
        $total = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentRecordStatus::Completed->value)
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }

    public function balanceDueForBooking(Booking $booking): string
    {
        $grand = (string) ($booking->total_amount ?? '0');
        if (bccomp($grand, '0', 2) <= 0) {
            return '0.00';
        }

        $paid = $this->sumCompletedForBooking($booking);

        $due = bcsub($grand, $paid, 2);

        return bccomp($due, '0', 2) < 0 ? '0.00' : $due;
    }

    /**
     * External card/bank capture for a deposit against the booking balance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordDeposit(
        Booking $booking,
        string $amount,
        ?User $recordedBy = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Payment {
        return $this->recordExternalBookingPayment(
            $booking,
            PaymentFlowType::Deposit,
            $amount,
            $recordedBy,
            $idempotencyKey,
            $metadata,
        );
    }

    /**
     * External capture for the full remaining balance (must equal balance due).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordFullPayment(
        Booking $booking,
        ?User $recordedBy = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Payment {
        $due = $this->balanceDueForBooking($booking);

        return $this->recordExternalBookingPayment(
            $booking,
            PaymentFlowType::FullPayment,
            $due,
            $recordedBy,
            $idempotencyKey,
            $metadata,
        );
    }

    /**
     * External capture for an arbitrary balance installment (partial pay-down).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordBalancePayment(
        Booking $booking,
        string $amount,
        ?User $recordedBy = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Payment {
        return $this->recordExternalBookingPayment(
            $booking,
            PaymentFlowType::BalanceDue,
            $amount,
            $recordedBy,
            $idempotencyKey,
            $metadata,
        );
    }

    /**
     * Top up agency prepaid wallet via the configured gateway (or manual simulator).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordWalletTopUp(
        Agency $agency,
        string $amount,
        ?User $recordedBy = null,
        ?string $idempotencyKey = null,
        string $currency = 'PKR',
        array $metadata = [],
    ): Payment {
        $this->assertPositiveAmount($amount);

        if ($idempotencyKey) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Payment) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($agency, $amount, $recordedBy, $idempotencyKey, $currency, $metadata): Payment {
            $payment = Payment::create([
                'agency_id' => $agency->id,
                'booking_id' => null,
                'recorded_by_user_id' => $recordedBy?->id,
                'flow_type' => PaymentFlowType::WalletTopUp->value,
                'source' => PaymentSource::ExternalGateway->value,
                'status' => PaymentRecordStatus::Processing->value,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'idempotency_key' => $idempotencyKey,
                'gateway_driver' => $this->gateway->driverKey(),
                'meta' => $metadata === [] ? null : $metadata,
            ]);

            $charge = $this->gateway->charge(new GatewayChargeRequest(
                amount: $amount,
                currency: $currency,
                booking: null,
                idempotencyKey: $idempotencyKey,
                metadata: array_merge($metadata, [
                    'payment_id' => $payment->id,
                    'flow' => PaymentFlowType::WalletTopUp->value,
                ]),
            ));

            $txn = PaymentGatewayTransaction::create([
                'payment_id' => $payment->id,
                'gateway_driver' => $this->gateway->driverKey(),
                'external_id' => $charge->externalId,
                'direction' => 'charge',
                'status' => $charge->status,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'raw_response' => $charge->raw,
            ]);

            if (! $charge->success || $charge->status !== GatewayTransactionStatus::Succeeded->value) {
                $payment->update([
                    'status' => PaymentRecordStatus::Failed->value,
                    'meta' => array_merge($payment->meta ?? [], [
                        'failure_message' => $charge->message,
                    ]),
                ]);

                return $payment->fresh();
            }

            $payment->update(['status' => PaymentRecordStatus::Completed->value]);

            $wallet = $this->ledger->ensureWallet($agency, $currency);
            $this->ledger->append(
                $agency,
                LedgerEntryType::WalletCredit,
                $amount,
                $currency,
                'Wallet top-up',
                booking: null,
                payment: $payment->fresh(),
                refund: null,
                meta: ['gateway_transaction_id' => $txn->id],
            );
            $this->ledger->touchLastPayment($wallet->fresh());

            return $payment->fresh();
        });
    }

    /**
     * Apply booking charges against agency prepaid wallet / credit line (no external gateway call).
     */
    public function payBookingFromWallet(
        Booking $booking,
        string $amount,
        ?User $recordedBy = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Payment {
        $this->assertPositiveAmount($amount);
        $due = $this->balanceDueForBooking($booking);
        if (bccomp($amount, $due, 2) > 0) {
            throw new InvalidPaymentAmountException('Amount exceeds remaining balance due.');
        }

        if (! $booking->agency_id) {
            throw new InvalidPaymentAmountException('Booking has no agency; wallet settlement is not available.');
        }

        if ($idempotencyKey) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Payment) {
                return $existing;
            }
        }

        $agency = $booking->agency()->firstOrFail();
        $currency = strtoupper((string) ($booking->currency ?? 'PKR'));

        return DB::transaction(function () use ($booking, $amount, $recordedBy, $idempotencyKey, $metadata, $agency, $currency): Payment {
            $wallet = $this->ledger->ensureWallet($agency, $currency);
            $this->ledger->assertSufficientBalanceForDebit($wallet, $amount);

            $signed = bcmul($amount, '-1', 2);

            $payment = Payment::create([
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'recorded_by_user_id' => $recordedBy?->id,
                'flow_type' => PaymentFlowType::BalanceDue->value,
                'source' => PaymentSource::AgencyWallet->value,
                'status' => PaymentRecordStatus::Completed->value,
                'amount' => $amount,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'gateway_driver' => 'internal_wallet',
                'meta' => $metadata === [] ? null : $metadata,
            ]);

            PaymentGatewayTransaction::create([
                'payment_id' => $payment->id,
                'gateway_driver' => 'internal_wallet',
                'external_id' => null,
                'direction' => 'charge',
                'status' => GatewayTransactionStatus::Succeeded->value,
                'amount' => $amount,
                'currency' => $currency,
                'raw_response' => ['internal' => true],
            ]);

            $this->ledger->append(
                $agency,
                LedgerEntryType::WalletDebit,
                $signed,
                $currency,
                'Booking wallet settlement',
                booking: $booking,
                payment: $payment,
                refund: null,
                meta: ['booking_id' => $booking->id],
            );
            $this->ledger->touchLastPayment($wallet->fresh());

            return $payment->fresh();
        });
    }

    /**
     * Refund a completed external payment via the active gateway (partial amounts supported).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function refundExternalPayment(
        Payment $payment,
        string $amount,
        ?string $reason = null,
        array $metadata = [],
    ): Refund {
        if ($payment->source !== PaymentSource::ExternalGateway->value) {
            throw new InvalidPaymentAmountException('Only external gateway payments can use gateway refunds through this method.');
        }

        if ($payment->status !== PaymentRecordStatus::Completed->value) {
            throw new InvalidPaymentAmountException('Payment is not completed.');
        }

        $this->assertPositiveAmount($amount);

        $parentTxn = $payment->gatewayTransactions()
            ->where('status', GatewayTransactionStatus::Succeeded->value)
            ->where('direction', 'charge')
            ->orderByDesc('id')
            ->first();

        if (! $parentTxn instanceof PaymentGatewayTransaction || ! $parentTxn->external_id) {
            throw new InvalidPaymentAmountException('No succeeded gateway transaction with external id found for refund.');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $metadata, $parentTxn): Refund {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'transaction_id' => $parentTxn->id,
                'status' => PaymentRecordStatus::Processing->value,
                'amount' => $amount,
                'currency' => $payment->currency,
                'gateway_refund_id' => null,
                'reason' => $reason,
                'meta' => $metadata === [] ? null : $metadata,
            ]);

            $result = $this->gateway->refund(new GatewayRefundRequest(
                amount: $amount,
                currency: $payment->currency,
                parentExternalId: $parentTxn->external_id,
                metadata: array_merge($metadata, [
                    'refund_id' => $refund->id,
                    'payment_id' => $payment->id,
                ]),
            ));

            if (! $result->success) {
                $refund->update([
                    'status' => PaymentRecordStatus::Failed->value,
                    'meta' => array_merge($refund->meta ?? [], [
                        'failure_message' => $result->message,
                        'raw' => $result->raw,
                    ]),
                ]);

                return $refund->fresh();
            }

            $refund->update([
                'status' => PaymentRecordStatus::Completed->value,
                'gateway_refund_id' => $result->externalRefundId,
                'meta' => array_merge($refund->meta ?? [], [
                    'raw' => $result->raw,
                ]),
            ]);

            return $refund->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordExternalBookingPayment(
        Booking $booking,
        PaymentFlowType $flow,
        string $amount,
        ?User $recordedBy,
        ?string $idempotencyKey,
        array $metadata,
    ): Payment {
        $this->assertPositiveAmount($amount);

        if (! $booking->agency_id) {
            throw new InvalidPaymentAmountException('Booking has no agency.');
        }

        $due = $this->balanceDueForBooking($booking);
        if (bccomp($due, '0', 2) <= 0) {
            throw new InvalidPaymentAmountException('Nothing is due for this booking.');
        }

        if ($flow === PaymentFlowType::FullPayment && bccomp($amount, $due, 2) !== 0) {
            throw new InvalidPaymentAmountException('Full payment amount must match balance due.');
        }

        if ($flow !== PaymentFlowType::FullPayment && bccomp($amount, $due, 2) > 0) {
            throw new InvalidPaymentAmountException('Amount exceeds balance due.');
        }

        if ($idempotencyKey) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Payment) {
                return $existing;
            }
        }

        $agency = $booking->agency()->firstOrFail();
        $currency = strtoupper((string) ($booking->currency ?? 'PKR'));

        return DB::transaction(function () use ($booking, $flow, $amount, $recordedBy, $idempotencyKey, $metadata, $agency, $currency): Payment {
            $payment = Payment::create([
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'recorded_by_user_id' => $recordedBy?->id,
                'flow_type' => $flow->value,
                'source' => PaymentSource::ExternalGateway->value,
                'status' => PaymentRecordStatus::Processing->value,
                'amount' => $amount,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'gateway_driver' => $this->gateway->driverKey(),
                'meta' => $metadata === [] ? null : $metadata,
            ]);

            $charge = $this->gateway->charge(new GatewayChargeRequest(
                amount: $amount,
                currency: $currency,
                booking: $booking,
                idempotencyKey: $idempotencyKey,
                metadata: array_merge($metadata, [
                    'payment_id' => $payment->id,
                    'flow' => $flow->value,
                ]),
            ));

            PaymentGatewayTransaction::create([
                'payment_id' => $payment->id,
                'gateway_driver' => $this->gateway->driverKey(),
                'external_id' => $charge->externalId,
                'direction' => 'charge',
                'status' => $charge->status,
                'amount' => $amount,
                'currency' => $currency,
                'raw_response' => $charge->raw,
            ]);

            if (! $charge->success || $charge->status !== GatewayTransactionStatus::Succeeded->value) {
                $payment->update([
                    'status' => PaymentRecordStatus::Failed->value,
                    'meta' => array_merge($payment->meta ?? [], [
                        'failure_message' => $charge->message,
                    ]),
                ]);

                return $payment->fresh();
            }

            $payment->update(['status' => PaymentRecordStatus::Completed->value]);

            $wallet = $this->ledger->ensureWallet($agency, $currency);
            $this->ledger->touchLastPayment($wallet);

            return $payment->fresh();
        });
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidPaymentAmountException('Amount must be greater than zero.');
        }
    }
}
