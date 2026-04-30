<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'request_type',
        'status',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reference_type',
        'reference_id',
        'reason',
        'review_note',
        'payload',
        'reference_url',
        'submitted_at',
        'expires_at',
        'reviewed_at',
        'consumed_at',
        'consumed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }
}
