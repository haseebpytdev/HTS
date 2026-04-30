<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceModuleCredential extends Model
{
    protected $table = 'service_module_credentials';

    protected $fillable = [
        'service_module_id',
        'credential_key',
        'credential_value_encrypted',
        'is_secret',
    ];

    protected $hidden = [
        'credential_value_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'credential_value_encrypted' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ServiceModule::class, 'service_module_id');
    }
}
