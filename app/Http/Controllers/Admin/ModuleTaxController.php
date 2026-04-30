<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleTaxRuleRequest;
use App\Services\Modules\ModuleTaxRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ModuleTaxController extends Controller
{
    public function __construct(
        private readonly ModuleTaxRuleService $tax,
    ) {
    }

    public function update(UpdateModuleTaxRuleRequest $request, string $module): RedirectResponse
    {
        $data = $request->validated();
        $this->tax->update(
            moduleKey: $module,
            taxType: (string) $data['tax_type'],
            taxValue: (float) $data['tax_value'],
            currencyId: isset($data['currency_id']) ? (int) $data['currency_id'] : null,
            actorUserId: Auth::id(),
        );

        return back()->with('success', 'Module tax rules updated.');
    }
}
