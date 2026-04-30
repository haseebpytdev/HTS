<?php

namespace App\Services\Payment;

use App\Enums\LedgerEntryType;
use App\Models\Agency;
use App\Models\AgencyWallet;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Finance\FinanceSettingsService;
use App\Services\Payment\Exceptions\CreditLimitExceededException;
use App\Services\Payment\Exceptions\InsufficientWalletBalanceException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function __construct(
        private readonly FinanceSettingsService $financeSettings,
    ) {
    }

    public function ensureWallet(Agency $agency, string $currency = 'PKR'): AgencyWallet
    {
        return AgencyWallet::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'currency' => $currency,
                'balance' => '0.00',
                'credit_limit' => null,
                'payment_terms_days' => $this->financeSettings->walletDefaultTermsDays(),
                'is_overdue' => false,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function append(
        Agency $agency,
        LedgerEntryType $type,
        string $signedAmount,
        string $currency,
        ?string $description = null,
        ?Booking $booking = null,
        ?Payment $payment = null,
        ?Refund $refund = null,
        array $meta = [],
    ): LedgerEntry {
        return DB::transaction(function () use ($agency, $type, $signedAmount, $currency, $description, $booking, $payment, $refund, $meta): LedgerEntry {
            $wallet = $this->ensureWallet($agency, $currency);
            if (strtoupper($wallet->currency) !== strtoupper($currency)) {
                throw new \InvalidArgumentException('Wallet currency does not match entry currency.');
            }

            $newBalance = bcadd((string) $wallet->balance, $signedAmount, 2);

            if (bccomp($signedAmount, '0', 2) < 0) {
                $this->assertDebitWithinCreditLine($wallet, $signedAmount);
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            $entry = LedgerEntry::create([
                'agency_id' => $agency->id,
                'booking_id' => $booking?->id,
                'payment_id' => $payment?->id,
                'refund_id' => $refund?->id,
                'entry_type' => $type->value,
                'amount' => $signedAmount,
                'currency' => strtoupper($currency),
                'description' => $description,
                'meta' => $meta === [] ? null : $meta,
            ]);

            $this->syncWalletDerivedFields($wallet->fresh());

            return $entry;
        });
    }

    public function assertDebitWithinCreditLine(AgencyWallet $wallet, string $signedDebitAmount): void
    {
        if (bccomp($signedDebitAmount, '0', 2) >= 0) {
            return;
        }

        $debit = bcmul($signedDebitAmount, '-1', 2);
        $balance = (string) $wallet->balance;
        $projected = bcsub($balance, $debit, 2);

        if ($wallet->credit_limit === null) {
            return;
        }

        $limit = (string) $wallet->credit_limit;
        $floor = bcmul($limit, '-1', 2);

        if (bccomp($projected, $floor, 2) < 0) {
            throw new CreditLimitExceededException('Agency would exceed configured credit limit.');
        }
    }

    /**
     * @throws InsufficientWalletBalanceException
     */
    public function assertSufficientBalanceForDebit(AgencyWallet $wallet, string $debitAmountPositive): void
    {
        if (bccomp($debitAmountPositive, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        $signed = bcmul($debitAmountPositive, '-1', 2);
        try {
            $this->assertDebitWithinCreditLine($wallet, $signed);
        } catch (CreditLimitExceededException) {
            throw new InsufficientWalletBalanceException('Insufficient wallet balance or credit for this debit.');
        }
    }

    public function syncWalletDerivedFields(AgencyWallet $wallet): void
    {
        $balance = (string) $wallet->balance;

        if (bccomp($balance, '0', 2) < 0) {
            if ($wallet->first_negative_balance_at === null) {
                $wallet->first_negative_balance_at = now();
            }
        } else {
            $wallet->first_negative_balance_at = null;
            $wallet->overdue_since = null;
            $wallet->is_overdue = false;
            $wallet->save();

            return;
        }

        $terms = (int) $wallet->payment_terms_days;
        if ($terms > 0 && $wallet->first_negative_balance_at instanceof Carbon) {
            $dueAt = $wallet->first_negative_balance_at->copy()->addDays($terms);
            if (now()->greaterThan($dueAt)) {
                $wallet->is_overdue = true;
                $wallet->overdue_since ??= $dueAt;
            } else {
                $wallet->is_overdue = false;
                $wallet->overdue_since = null;
            }
        } else {
            $wallet->is_overdue = false;
            $wallet->overdue_since = null;
        }

        $wallet->save();
    }

    public function touchLastPayment(AgencyWallet $wallet): void
    {
        $wallet->last_payment_at = now();
        $wallet->save();
    }
}
