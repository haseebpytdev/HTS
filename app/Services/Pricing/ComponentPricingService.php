<?php

namespace App\Services\Pricing;

use App\Data\Calculator\PricingComponentInput;

class ComponentPricingService
{
    public function calculate(PricingComponentInput $component, int $passengers): float
    {
        if ($component->mode === 'per_person') {
            return round($component->amount * $passengers, 2);
        }

        return round($component->amount, 2);
    }
}
