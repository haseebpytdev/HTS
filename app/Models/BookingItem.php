<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'item_type',
        'reference_type',
        'reference_id',
        'title',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'meta',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
