<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOfferSnapshot extends Model
{
    protected $fillable = [
        'supplier_search_session_id',
        'offer_key',
        'provider_offer_reference',
        'normalized_offer',
        'selected_fare_summary',
        'is_selected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'normalized_offer' => 'array',
            'selected_fare_summary' => 'array',
            'is_selected' => 'boolean',
        ];
    }

    public function searchSession(): BelongsTo
    {
        return $this->belongsTo(SupplierSearchSession::class, 'supplier_search_session_id');
    }
}
