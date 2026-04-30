<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceModule extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'module_key',
        'provider_code',
        'provider_name',
        'provider',
        'provider_logo_url',
        'logo_path',
        'service_type',
        'environment',
        'is_active',
        'is_default',
        'is_default_provider',
        'status',
        'connection_status',
        'provider_priority',
        'allow_fallback',
        'allow_multi_provider',
        'last_tested_at',
        'last_success_at',
        'last_failure_at',
        'last_failure_reason',
        'supported_operations_json',
        'sort_order',
        'available_operations',
        'documentation_url',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_default_provider' => 'boolean',
            'allow_fallback' => 'boolean',
            'allow_multi_provider' => 'boolean',
            'supported_operations_json' => 'array',
            'available_operations' => 'array',
            'config' => 'array',
            'last_tested_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(ServiceModuleSetting::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ServiceModulePricingRule::class, 'service_module_id');
    }

    public function taxRules(): HasMany
    {
        return $this->hasMany(ServiceModuleTaxRule::class, 'service_module_id');
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ServiceModuleHealthCheck::class, 'service_module_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ServiceModuleCredential::class, 'service_module_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(TenantProviderAccess::class, 'provider', 'provider');
    }

    public function latestPricingRule(): HasOne
    {
        return $this->hasOne(ServiceModulePricingRule::class, 'service_module_id')->latestOfMany();
    }

    public function latestTaxRule(): HasOne
    {
        return $this->hasOne(ServiceModuleTaxRule::class, 'service_module_id')->latestOfMany();
    }

    public function latestHealthCheck(): HasOne
    {
        return $this->hasOne(ServiceModuleHealthCheck::class, 'service_module_id')->latestOfMany('checked_at');
    }
}
