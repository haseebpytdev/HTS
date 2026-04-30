<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantIntegrationPolicy extends Model
{
    protected $fillable = [
        'tenant_id',
        'allowed_providers',
        'provider_permissions',
        'allow_multi_provider',
        'allow_fallback',
        'provider_priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_providers' => 'array',
            'provider_permissions' => 'array',
            'allow_multi_provider' => 'boolean',
            'allow_fallback' => 'boolean',
            'provider_priority' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
