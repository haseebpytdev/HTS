<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleAccess extends Model
{
    protected $table = 'tenant_module_access';

    protected $fillable = [
        'tenant_id',
        'module_code',
        'is_enabled',
        'allowed_operations',
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
            'is_enabled' => 'boolean',
            'allowed_operations' => 'array',
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
