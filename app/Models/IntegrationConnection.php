<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'account_key',
        'tenant_id',
        'account_name',
        'ownership_type',
        'ownership_tenant_id',
        'provider',
        'environment',
        'base_url',
        'config',
        'is_active',
        'is_default',
        'status',
        'last_tested_at',
        'last_failure_at',
        'supported_operations',
        'created_by',
        'updated_by',
        'last_tested_status',
        'last_checked_at',
        'last_success_at',
        'last_failure_reason',
        'token_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'supported_operations' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'token_expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ownershipTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'ownership_tenant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(IntegrationToken::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(IntegrationRequestLog::class);
    }

    public function integrationEvents(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }
}
