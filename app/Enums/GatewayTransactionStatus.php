<?php

namespace App\Enums;

enum GatewayTransactionStatus: string
{
    case Initiated = 'initiated';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::RequiresAction => 'Requires action',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }
}
