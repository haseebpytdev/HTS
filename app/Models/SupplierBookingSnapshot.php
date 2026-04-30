<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBookingSnapshot extends Model
{
    protected $fillable = [
        'correlation_id',
        'integration_connection_id',
        'integration_request_log_id',
        'provider',
        'booking_reference',
        'pnr',
        'internal_status',
        'normalized_totals',
        'travelers_json',
        'booking_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'normalized_totals' => 'array',
            'travelers_json' => 'array',
            'booking_summary' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(IntegrationRequestLog::class, 'integration_request_log_id');
    }
}
