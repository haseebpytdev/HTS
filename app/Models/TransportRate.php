<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'transport_type_id',
        'agency_id',
        'vehicle_name',
        'route_from',
        'route_to',
        'trip_type',
        'currency',
        'amount',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function transportType(): BelongsTo
    {
        return $this->belongsTo(TransportType::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
