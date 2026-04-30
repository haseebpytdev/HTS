<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExportHistory extends Model
{
    public const TYPE_QUOTATION_PDF = 'quotation_pdf';

    public const TYPE_INQUIRIES_CSV = 'inquiries_csv';

    public const TYPE_BOOKINGS_CSV = 'bookings_csv';

    protected $fillable = [
        'user_id',
        'export_type',
        'reference_type',
        'reference_id',
        'meta',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
