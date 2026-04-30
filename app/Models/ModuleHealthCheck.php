<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleHealthCheck extends Model
{
    protected $fillable = [
        'service_module_id',
        'connection_status',
        'health_status',
        'health_score',
        'latency_ms',
        'last_error',
        'last_checked_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
