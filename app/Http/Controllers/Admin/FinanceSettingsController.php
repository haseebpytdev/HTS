<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFinanceSettingsRequest;
use App\Services\Finance\FinanceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceSettingsController extends Controller
{
    public function __construct(
        private readonly FinanceSettingsService $settings,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('manage-tenancy-settings');

        return view('admin.finance.settings', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(UpdateFinanceSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tenancy-settings');
        $this->settings->save($request->validated());

        return redirect()
            ->route('admin.finance.settings.edit')
            ->with('success', 'Finance settings updated.');
    }
}
