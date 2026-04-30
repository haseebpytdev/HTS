<?php

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenancySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app(TenancySettings::class)->tenantScopingEnabled()) {
            return;
        }

        $tenantId = app(TenantContext::class)->scopedTenantId();
        if ($tenantId === null) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
