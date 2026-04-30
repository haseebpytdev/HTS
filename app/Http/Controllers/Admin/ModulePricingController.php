<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModulePricingRuleRequest;
use App\Services\Modules\ModulePricingRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ModulePricingController extends Controller
{
    public function __construct(
        private readonly ModulePricingRuleService $pricing,
    ) {
    }

    public function update(UpdateModulePricingRuleRequest $request, string $module): RedirectResponse
    {
        $data = $request->validated();
        $this->pricing->update(
            moduleKey: $module,
            b2bType: (string) $data['markup_type_b2b'],
            b2bValue: (float) $data['markup_value_b2b'],
            b2cType: (string) $data['markup_type_b2c'],
            b2cValue: (float) $data['markup_value_b2c'],
            baseCurrency: (string) $data['base_currency_code'],
            actorUserId: Auth::id(),
        );

        return back()->with('success', 'Module pricing rules updated.');
    }
}
