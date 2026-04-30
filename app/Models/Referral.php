<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referral_code',
        'referrer_customer_id',
        'referred_customer_id',
        'referred_booking_id',
        'status',
        'reward_amount',
        'reward_currency',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_amount' => 'decimal:2',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referredCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'referred_booking_id');
    }
}
