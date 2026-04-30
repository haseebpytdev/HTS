<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceModulePricingRule extends Model
{
    protected $table = 'service_module_pricing_rules';

    protected $fillable = [
        'service_module_id',
        'markup_type_b2b',
        'markup_value_b2b',
        'markup_type_b2c',
        'markup_value_b2c',
        'base_currency_code',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
