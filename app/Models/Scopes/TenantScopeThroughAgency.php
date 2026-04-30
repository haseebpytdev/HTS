<?php

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenancySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScopeThroughAgency implements Scope
{
    public function __construct(
        private readonly string $agencyForeignKey = 'agency_id',
    ) {
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (! app(TenancySettings::class)->tenantScopingEnabled()) {
            return;
        }

        $tenantId = app(TenantContext::class)->scopedTenantId();
        if ($tenantId === null) {
            return;
        }

        $table = $model->getTable();
        $fk = $this->agencyForeignKey;

        $builder->whereExists(function ($q) use ($table, $fk, $tenantId): void {
            $q->selectRaw('1')
                ->from('agencies')
                ->whereColumn('agencies.id', "{$table}.{$fk}")
                ->where('agencies.tenant_id', $tenantId);
        });
    }
}
