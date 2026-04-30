<?php

namespace App\Enums;

enum PaymentFlowType: string
{
    case Deposit = 'deposit';
    case FullPayment = 'full_payment';
    case BalanceDue = 'balance_due';
    case WalletTopUp = 'wallet_top_up';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::FullPayment => 'Full payment',
            self::BalanceDue => 'Balance due',
            self::WalletTopUp => 'Wallet top-up',
        };
    }
}
