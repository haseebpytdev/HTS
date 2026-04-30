<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyWallet extends Model
{
    protected $fillable = [
        'agency_id',
        'currency',
        'balance',
        'credit_limit',
        'payment_terms_days',
        'last_payment_at',
        'first_negative_balance_at',
        'overdue_since',
        'is_overdue',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'last_payment_at' => 'datetime',
            'first_negative_balance_at' => 'datetime',
            'overdue_since' => 'datetime',
            'is_overdue' => 'boolean',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
