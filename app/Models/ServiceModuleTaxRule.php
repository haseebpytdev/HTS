<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceModuleTaxRule extends Model
{
    protected $table = 'service_module_tax_rules';

    protected $fillable = [
        'service_module_id',
        'tax_type',
        'tax_value',
        'currency_id',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
