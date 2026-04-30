<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Scopes\TenantScopeThroughAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'agency_id',
        'user_id',
        'inquiry_id',
        'quote_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'travel_date',
        'return_date',
        'adults',
        'children',
        'infants',
        'currency',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'promo_code_id',
        'promo_code',
        'promo_discount_amount',
        'total_amount',
        'status',
        'notes',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'return_date' => 'date',
            'expires_at' => 'datetime',
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

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(QuotationRevisionRequest::class);
    }

    public function bookingIntents(): HasMany
    {
        return $this->hasMany(BookingIntent::class);
    }
}
