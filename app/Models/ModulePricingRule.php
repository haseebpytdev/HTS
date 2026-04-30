<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePricingRule extends Model
{
    protected $fillable = [
        'service_module_id',
        'channel',
        'markup_type',
        'markup_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
