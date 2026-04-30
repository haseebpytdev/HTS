<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceModuleSetting extends Model
{
    protected $fillable = [
        'service_module_id',
        'b2b_markup_type',
        'b2b_markup_value',
        'b2c_markup_type',
        'b2c_markup_value',
        'base_currency',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
