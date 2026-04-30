<?php

namespace App\Models;

use App\Enums\LeadPipelineStage;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Scopes\TenantScopeThroughAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'source',
        'agency_id',
        'user_id',
        'destination_id',
        'package_id',
        'group_id',
        'assigned_to',
        'name',
        'email',
        'phone',
        'travel_date',
        'adults',
        'children',
        'budget',
        'currency',
        'message',
        'admin_notes',
        'status',
        'pipeline_stage',
        'estimated_value',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'pipeline_stage' => LeadPipelineStage::class,
            'estimated_value' => 'decimal:2',
            'last_contacted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScopeThroughAgency);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'package_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TravelGroup::class, 'group_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InquiryActivity::class)->orderByDesc('occurred_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(InquiryFollowUp::class)->orderBy('due_at');
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_CONTACTED,
            self::STATUS_QUOTED,
            self::STATUS_REVISION_REQUESTED,
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
        ];
    }
}
