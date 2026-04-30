<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;
use App\Policies\Concerns\ChecksTenancyAccess;

class InquiryPolicy
{
    use ChecksTenancyAccess;

    public function view(User $user, Inquiry $inquiry): bool
    {
        $tenantId = $inquiry->agency?->tenant_id;

        return $this->tenancyAllows($user, $tenantId);
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }
}
