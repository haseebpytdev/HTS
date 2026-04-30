<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Referral;

class ReferralService
{
    public function createCodeForCustomer(Customer $customer): Referral
    {
        return Referral::query()->create([
            'referral_code' => $this->generateCode($customer->id),
            'referrer_customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    public function recordConversion(string $referralCode, Customer $referredCustomer): ?Referral
    {
        $referral = Referral::query()
            ->whereRaw('lower(referral_code) = ?', [mb_strtolower(trim($referralCode))])
            ->first();

        if (! $referral) {
            return null;
        }

        $referral->forceFill([
            'referred_customer_id' => $referredCustomer->id,
            'status' => 'converted',
        ])->save();

        return $referral->fresh();
    }

    private function generateCode(int $customerId): string
    {
        return 'REF-'.$customerId.'-'.strtoupper(substr((string) str()->uuid(), 0, 8));
    }
}
