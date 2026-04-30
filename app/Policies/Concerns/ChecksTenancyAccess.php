<?php

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Tenancy\TenancySettings;

trait ChecksTenancyAccess
{
    protected function tenancyAllows(User $user, ?int $resourceTenantId): bool
    {
        $settings = app(TenancySettings::class);
        if (! $settings->tenantScopingEnabled()) {
            return true;
        }

        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        if ($user->tenant_id === null) {
            return true;
        }

        return $resourceTenantId !== null && $resourceTenantId === $user->tenant_id;
    }
}
