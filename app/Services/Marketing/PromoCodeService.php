<?php

namespace App\Services\Marketing;

use App\Models\PromoCode;

class PromoCodeService
{
    public function validateForAmount(string $code, float $amount): ?PromoCode
    {
        $promo = PromoCode::query()
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])
            ->where('is_active', true)
            ->first();

        if (! $promo) {
            return null;
        }

        $now = now();
        if ($promo->starts_at && $promo->starts_at->gt($now)) {
            return null;
        }
        if ($promo->expires_at && $promo->expires_at->lt($now)) {
            return null;
        }
        if ($promo->usage_limit !== null && $promo->usage_count >= $promo->usage_limit) {
            return null;
        }

        $discount = $this->discountAmount($promo, $amount);
        if ($discount <= 0) {
            return null;
        }

        return $promo;
    }

    public function discountAmount(PromoCode $promo, float $amount): float
    {
        $amount = max(0, $amount);
        $discount = $promo->discount_type === 'fixed'
            ? (float) $promo->discount_value
            : ($amount * ((float) $promo->discount_value / 100));

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return max(0, min($discount, $amount));
    }
}
