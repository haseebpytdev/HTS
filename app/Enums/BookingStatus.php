<?php

namespace App\Enums;

enum BookingStatus: string
{
    /** Legacy / pre-engine rows */
    case Pending = 'pending';

    case Draft = 'draft';
    case OnHold = 'on_hold';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Draft => 'Draft',
            self::OnHold => 'On hold',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
        };
    }
}
