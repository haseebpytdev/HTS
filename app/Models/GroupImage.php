<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_cover',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TravelGroup::class, 'group_id');
    }
}
