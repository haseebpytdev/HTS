<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case WalletCredit = 'wallet_credit';
    case WalletDebit = 'wallet_debit';
    case RefundCredit = 'refund_credit';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::WalletCredit => 'Wallet credit',
            self::WalletDebit => 'Wallet debit',
            self::RefundCredit => 'Refund credit',
            self::Adjustment => 'Adjustment',
        };
    }
}
