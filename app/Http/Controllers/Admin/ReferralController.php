<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Referral;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService
    ) {
    }

    public function index(): View
    {
        $referrals = Referral::query()->with(['referrer', 'referredCustomer', 'booking'])->latest('id')->paginate(20);
        $customers = Customer::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin/referrals/index', compact('referrals', 'customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referrer_customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail((int) $validated['referrer_customer_id']);
        $this->referralService->createCodeForCustomer($customer);

        return redirect()->route('admin.referrals.index')->with('success', 'Referral code generated.');
    }
}
