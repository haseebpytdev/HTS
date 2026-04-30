<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\Concerns\ChecksTenancyAccess;

class BookingPolicy
{
    use ChecksTenancyAccess;

    public function view(User $user, Booking $booking): bool
    {
        $tenantId = $booking->agency?->tenant_id;

        return $this->tenancyAllows($user, $tenantId);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
