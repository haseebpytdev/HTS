<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'integration_connection_id',
        'provider',
        'operation',
        'environment',
        'correlation_id',
        'trace_id',
        'http_method',
        'url',
        'request_headers',
        'request_body',
        'user_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responseLog(): HasOne
    {
        return $this->hasOne(IntegrationResponseLog::class);
    }
}
