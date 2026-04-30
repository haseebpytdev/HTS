<?php

namespace App\Services\Tenancy;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class TenantContext
{
    public function __construct(
        private readonly TenancySettings $settings,
    ) {
    }

    /**
     * Tenant id to apply for automatic query scoping, or null to skip scoping (super admin, legacy users, scoping off).
     */
    public function scopedTenantId(?Authenticatable $user = null): ?int
    {
        if (! $this->settings->tenantScopingEnabled()) {
            return null;
        }

        $user = $user ?? auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if ($user->role === UserRole::SUPER_ADMIN) {
            return null;
        }

        return $user->tenant_id;
    }
}
