<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'origin',
        'destination',
        'airline',
        'flight_no',
        'depart_at',
        'arrive_at',
        'cabin_class',
        'seats_available',
        'currency',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'arrive_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
