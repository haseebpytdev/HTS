<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSearchSession extends Model
{
    protected $fillable = [
        'correlation_id',
        'integration_connection_id',
        'provider',
        'environment',
        'internal_request_snapshot',
        'search_results_summary',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'internal_request_snapshot' => 'array',
            'search_results_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function offerSnapshots(): HasMany
    {
        return $this->hasMany(SupplierOfferSnapshot::class);
    }
}
