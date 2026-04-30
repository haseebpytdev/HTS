<?php

namespace App\Enums;

enum PaymentSource: string
{
    case ExternalGateway = 'external_gateway';
    case AgencyWallet = 'agency_wallet';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::ExternalGateway => 'External gateway',
            self::AgencyWallet => 'Agency wallet',
            self::Manual => 'Manual',
        };
    }
}
