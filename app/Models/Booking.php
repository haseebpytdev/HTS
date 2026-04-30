<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Scopes\TenantScopeThroughAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'quotation_id',
        'agency_id',
        'user_id',
        'customer_id',
        'booking_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'travel_date',
        'return_date',
        'subtotal',
        'tax_amount',
        'promo_code_id',
        'promo_code',
        'promo_discount_amount',
        'discount_amount',
        'status',
        'booked_at',
        'hold_expires_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'total_amount',
        'supplier_cost_total',
        'supplier_cost_currency',
        'supplier_cost_recorded_at',
        'supplier_cost_recorded_by_user_id',
        'currency',
        'payment_status',
        'remarks',
        'internal_notes',
        'invoice_number',
        'invoice_issued_at',
        'supplier_flight_hook_status',
        'supplier_hotel_hook_status',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'invoice_issued_at' => 'datetime',
            'supplier_cost_recorded_at' => 'datetime',
            'travel_date' => 'date',
            'return_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScopeThroughAgency);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplierCostRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_cost_recorded_by_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('sort_order');
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(Traveler::class)->orderBy('sort_order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderByDesc('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class)->withTrashed()->orderByDesc('id');
    }

    public function statusEnum(): ?BookingStatus
    {
        return BookingStatus::tryFrom($this->status);
    }
}
