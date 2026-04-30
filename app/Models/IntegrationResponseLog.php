<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationResponseLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'integration_request_log_id',
        'correlation_id',
        'status_code',
        'response_headers',
        'response_body',
        'latency_ms',
        'error_category',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(IntegrationRequestLog::class, 'integration_request_log_id');
    }
}
