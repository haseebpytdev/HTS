<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Marketing\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function __construct(
        private readonly PromoCodeService $promoCodeService
    ) {
    }

    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $promo = $this->promoCodeService->validateForAmount((string) $validated['code'], (float) $validated['amount']);
        if (! $promo) {
            return response()->json([
                'valid' => false,
                'message' => 'Promo code is invalid or expired.',
            ], 422);
        }

        $discount = $this->promoCodeService->discountAmount($promo, (float) $validated['amount']);

        return response()->json([
            'valid' => true,
            'code' => $promo->code,
            'discount' => round($discount, 2),
            'discount_type' => $promo->discount_type,
        ]);
    }
}
