<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency_code',
        'target_currency_code',
        'rate',
        'effective_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'effective_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
