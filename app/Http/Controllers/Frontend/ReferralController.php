<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService
    ) {
    }

    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'referral_code' => ['required', 'string', 'max:80'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail((int) $validated['customer_id']);
        $referral = $this->referralService->recordConversion((string) $validated['referral_code'], $customer);
        if (! $referral) {
            return response()->json(['success' => false, 'message' => 'Referral code not found.'], 404);
        }

        return response()->json(['success' => true, 'referral_id' => $referral->id]);
    }
}
