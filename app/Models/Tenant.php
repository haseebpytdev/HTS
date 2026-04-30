<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan_tier',
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
            'plan_tier' => 'string',
            'usage_quota_json' => 'array',
            'soft_limit_percent' => 'integer',
            'hard_limit_enforced' => 'boolean',
            'overage_alert_enabled' => 'boolean',
        ];
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function integrationPolicy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantIntegrationPolicy::class);
    }

    public function providerAccesses(): HasMany
    {
        return $this->hasMany(TenantProviderAccess::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ServiceModule::class);
    }

    public function moduleAccesses(): HasMany
    {
        return $this->hasMany(TenantModuleAccess::class);
    }

    /**
     * Primary tenant for legacy and single-tenant deployments (slug from config).
     */
    public static function defaultModel(): self
    {
        $slug = config('tenancy.default_slug', 'default');

        return static::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Safe for use after migrations; returns null only if tenants table is empty.
     */
    public static function defaultId(): ?int
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $slug = config('tenancy.default_slug', 'default');

        return static::query()->where('slug', $slug)->value('id');
    }
}
