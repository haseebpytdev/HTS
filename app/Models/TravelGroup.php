<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelGroup extends Model
{
    use HasFactory;

    protected $table = 'groups';

    protected $fillable = [
        'package_id',
        'agency_id',
        'name',
        'slug',
        'group_type',
        'departure_date',
        'return_date',
        'capacity',
        'seats_left',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'package_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GroupImage::class, 'group_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'group_id');
    }
}
