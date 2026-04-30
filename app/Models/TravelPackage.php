<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPackage extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = [
        'category_id',
        'destination_id',
        'agency_id',
        'title',
        'slug',
        'excerpt',
        'description',
        'duration_days',
        'base_price',
        'currency',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(PackageDeparture::class, 'package_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class, 'package_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TravelGroup::class, 'package_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'package_id');
    }
}
