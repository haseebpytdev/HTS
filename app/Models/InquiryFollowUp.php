<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryFollowUp extends Model
{
    protected $fillable = [
        'inquiry_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'due_at',
        'completed_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $inquiry = request()->route('inquiry');
        $inquiryId = $inquiry instanceof Inquiry ? $inquiry->getKey() : $inquiry;

        return static::query()
            ->where('inquiry_id', $inquiryId)
            ->whereKey($value)
            ->firstOrFail();
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOpen(): bool
    {
        return $this->completed_at === null;
    }
}
