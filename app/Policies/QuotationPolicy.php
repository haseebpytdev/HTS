<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenancyAccess;

class QuotationPolicy
{
    use ChecksTenancyAccess;

    public function view(User $user, Quotation $quotation): bool
    {
        $tenantId = $quotation->agency?->tenant_id;

        return $this->tenancyAllows($user, $tenantId);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation);
    }
}
