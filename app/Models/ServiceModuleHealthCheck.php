<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceModuleHealthCheck extends Model
{
    protected $table = 'service_module_health_checks';

    protected $fillable = [
        'service_module_id',
        'result_status',
        'checked_at',
        'latency_ms',
        'message',
        'raw_response_json',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'raw_response_json' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
