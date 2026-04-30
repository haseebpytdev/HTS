<?php

namespace Tests\Unit\Payment;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentRecordStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Quotation;
use App\Services\Payment\Exceptions\InsufficientWalletBalanceException;
use App\Services\Payment\LedgerService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookingWithTotal(Agency $agency, string $total = '1000.00'): Booking
    {
        $quotation = Quotation::create([
            'agency_id' => $agency->id,
            'quote_number' => 'Q-PAY-'.uniqid(),
            'customer_name' => 'Pay Test',
            'currency' => 'PKR',
            'subtotal' => $total,
            'tax_amount' => '0',
            'discount_amount' => '0',
            'total_amount' => $total,
            'status' => 'draft',
        ]);

        return Booking::create([
            'quotation_id' => $quotation->id,
            'agency_id' => $agency->id,
            'booking_number' => 'B-PAY-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => $total,
            'currency' => 'PKR',
        ]);
    }

    public function test_deposit_reduces_balance_due_and_completes_with_manual_gateway(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '500.00');
        $payments = $this->app->make(PaymentService::class);

        $payment = $payments->recordDeposit($booking, '200.00', null, 'idem-deposit-1');

        $this->assertSame(PaymentRecordStatus::Completed->value, $payment->status);
        $this->assertSame('200.00', $payments->sumCompletedForBooking($booking));
        $this->assertSame('300.00', $payments->balanceDueForBooking($booking->fresh()));
    }

    public function test_idempotent_deposit_returns_same_payment(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency);
        $payments = $this->app->make(PaymentService::class);

        $a = $payments->recordDeposit($booking, '100.00', null, 'idem-same');
        $b = $payments->recordDeposit($booking, '100.00', null, 'idem-same');

        $this->assertSame($a->id, $b->id);
    }

    public function test_full_payment_requires_exact_balance_due(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '400.00');
        $payments = $this->app->make(PaymentService::class);

        $payments->recordDeposit($booking, '100.00', null, 'p1');
        $full = $payments->recordFullPayment($booking, null, 'full-1');

        $this->assertSame(PaymentFlowType::FullPayment->value, $full->flow_type);
        $this->assertSame('300.00', $full->amount);
        $this->assertSame('0.00', $payments->balanceDueForBooking($booking->fresh()));
    }

    public function test_balance_payment_reduces_remaining_due_like_deposit(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '900.00');
        $payments = $this->app->make(PaymentService::class);

        $payments->recordDeposit($booking, '100.00', null, 'dep-bal');
        $bal = $payments->recordBalancePayment($booking, '250.00', null, 'bal-pay-1');

        $this->assertSame(PaymentFlowType::BalanceDue->value, $bal->flow_type);
        $this->assertSame('550.00', $payments->balanceDueForBooking($booking->fresh()));
        $this->assertSame('350.00', $payments->sumCompletedForBooking($booking->fresh()));
    }

    public function test_wallet_top_up_and_booking_settlement(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '250.00');
        $payments = $this->app->make(PaymentService::class);
        $ledger = $this->app->make(LedgerService::class);

        $payments->recordWalletTopUp($agency, '500.00', null, 'top-1', 'PKR');

        $wallet = $ledger->ensureWallet($agency);
        $this->assertSame('500.00', (string) $wallet->fresh()->balance);

        $this->assertTrue(
            LedgerEntry::query()
                ->where('agency_id', $agency->id)
                ->where('entry_type', LedgerEntryType::WalletCredit->value)
                ->exists()
        );

        $payments->payBookingFromWallet($booking, '250.00', null, 'wallet-pay-1');

        $this->assertSame('250.00', (string) $wallet->fresh()->balance);
        $this->assertSame('0.00', $payments->balanceDueForBooking($booking->fresh()));

        $this->assertTrue(
            LedgerEntry::query()
                ->where('agency_id', $agency->id)
                ->where('booking_id', $booking->id)
                ->where('entry_type', LedgerEntryType::WalletDebit->value)
                ->exists()
        );
    }

    public function test_wallet_pay_respects_zero_credit_limit(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '100.00');
        $payments = $this->app->make(PaymentService::class);
        $ledger = $this->app->make(LedgerService::class);

        $w = $ledger->ensureWallet($agency);
        $w->update(['credit_limit' => '0.00', 'balance' => '0.00']);

        $this->expectException(InsufficientWalletBalanceException::class);
        $payments->payBookingFromWallet($booking, '100.00', null, 'fail-wallet');
    }

    public function test_refund_external_payment_persists_refund_row(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $booking = $this->makeBookingWithTotal($agency, '100.00');
        $payments = $this->app->make(PaymentService::class);

        $payment = $payments->recordDeposit($booking, '100.00', null, 'pay-r1');
        $refund = $payments->refundExternalPayment($payment, '40.00', 'partial');

        $this->assertSame(PaymentRecordStatus::Completed->value, $refund->status);
        $this->assertNotNull($refund->gateway_refund_id);
    }

    public function test_overdue_flag_when_negative_balance_exceeds_terms(): void
    {
        $agency = Agency::create(['name' => 'Pay Agency', 'code' => 'PAY-'.uniqid(), 'is_active' => true]);
        $ledger = $this->app->make(LedgerService::class);
        $wallet = $ledger->ensureWallet($agency);
        $wallet->update([
            'balance' => '-50.00',
            'payment_terms_days' => 7,
            'first_negative_balance_at' => now()->subDays(10),
            'is_overdue' => false,
        ]);

        $ledger->syncWalletDerivedFields($wallet->fresh());

        $this->assertTrue($wallet->fresh()->is_overdue);
        $this->assertNotNull($wallet->fresh()->overdue_since);
    }
}
