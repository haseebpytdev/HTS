<?php

namespace App\Data\Calculator;

class PricingComponentInput
{
    public function __construct(
        public readonly string $name,
        public readonly float $amount,
        public readonly string $mode = 'fixed'
    ) {
    }
}
