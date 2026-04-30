<?php

namespace App\Services\Customer;

use App\Models\Booking;
use App\Models\Customer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CustomerBookingAccess
{
    public function ensure(Customer $customer, Booking $booking): void
    {
        if ($booking->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('You do not have access to this booking.');
        }
    }
}
