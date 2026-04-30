<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSavedTraveler extends Model
{
    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->where('customer_id', auth('customer')->id())
            ->firstOrFail();
    }
    protected $fillable = [
        'customer_id',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
