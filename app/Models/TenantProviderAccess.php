<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProviderAccess extends Model
{
    protected $table = 'tenant_provider_access';

    protected $fillable = [
        'tenant_id',
        'provider',
        'environment',
        'plan_code',
        'can_search',
        'can_price',
        'can_book',
        'allow_multi_provider',
        'allow_fallback',
        'priority_order',
        'is_enabled',
        'usage_quota_json',
        'soft_limit_percent',
        'hard_limit_enforced',
        'overage_alert_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_search' => 'boolean',
            'can_price' => 'boolean',
            'can_book' => 'boolean',
            'allow_multi_provider' => 'boolean',
            'allow_fallback' => 'boolean',
            'is_enabled' => 'boolean',
            'usage_quota_json' => 'array',
            'soft_limit_percent' => 'integer',
            'hard_limit_enforced' => 'boolean',
            'overage_alert_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
