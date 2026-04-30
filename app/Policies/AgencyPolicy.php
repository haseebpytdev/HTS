<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;
use App\Policies\Concerns\ChecksTenancyAccess;

class AgencyPolicy
{
    use ChecksTenancyAccess;

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->tenancyAllows($user, $agency->tenant_id);
    }

    public function update(User $user, Agency $agency): bool
    {
        return $this->tenancyAllows($user, $agency->tenant_id);
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $this->tenancyAllows($user, $agency->tenant_id);
    }
}
