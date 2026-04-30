<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingDocument extends Model
{
    use SoftDeletes;

    public const SCAN_PENDING = 'pending_scan';
    public const SCAN_CLEAN = 'clean';
    public const SCAN_INFECTED = 'infected';
    public const SCAN_SUSPICIOUS = 'suspicious';
    public const SCAN_FAILED = 'failed';
    public const SCAN_SKIPPED = 'skipped';

    protected $fillable = [
        'booking_id',
        'uploaded_by_user_id',
        'uploaded_by_customer_id',
        'validated_by_user_id',
        'document_type',
        'document_group_uuid',
        'version_number',
        'superseded_by_document_id',
        'validation_status',
        'virus_scan_status',
        'virus_scanned_at',
        'virus_scan_note',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'is_customer_visible',
        'notes',
        'validation_note',
        'validated_at',
        'archived_at',
        'archived_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
            'validated_at' => 'datetime',
            'archived_at' => 'datetime',
            'virus_scanned_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function uploadedByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'uploaded_by_customer_id');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function supersededByDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_document_id');
    }

    public function previousVersions(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_document_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BookingDocumentAudit::class)->orderByDesc('id');
    }

    public function isScanSafe(): bool
    {
        return $this->virus_scan_status === self::SCAN_CLEAN;
    }

    public function hasScanCompleted(): bool
    {
        return in_array($this->virus_scan_status, [
            self::SCAN_CLEAN,
            self::SCAN_INFECTED,
            self::SCAN_SUSPICIOUS,
            self::SCAN_FAILED,
            self::SCAN_SKIPPED,
        ], true);
    }

    public function isQuarantined(): bool
    {
        return in_array($this->virus_scan_status, [
            self::SCAN_INFECTED,
            self::SCAN_SUSPICIOUS,
            self::SCAN_FAILED,
        ], true);
    }
}
