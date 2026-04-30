<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traveler extends Model
{
    public const TYPE_ADULT = 'adult';

    public const TYPE_CHILD = 'child';

    public const TYPE_INFANT = 'infant';

    protected $fillable = [
        'booking_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'passport_no',
        'nationality',
        'traveler_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
