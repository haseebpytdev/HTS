<?php

namespace App\Models;

use App\Enums\InquiryActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryActivity extends Model
{
    protected $fillable = [
        'inquiry_id',
        'user_id',
        'type',
        'title',
        'body',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InquiryActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
