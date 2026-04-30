<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenancySettingsRequest;
use App\Services\System\SystemSettingsService;
use App\Services\Tenancy\TenancySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenancySettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function edit(TenancySettings $tenancySettings): View
    {
        $this->authorize('manage-tenancy-settings');

        return view('admin.system.tenancy', [
            'tenantScopingEnabled' => $tenancySettings->tenantScopingEnabled(),
        ]);
    }

    public function update(UpdateTenancySettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tenancy-settings');

        $enabled = (bool) $request->validated('tenant_scoping_enabled');
        $this->settings->set(
            key: TenancySettings::KEY_TENANT_SCOPING_ENABLED,
            value: $enabled,
            context: [
                'scope' => 'platform',
                'category' => 'tenancy',
            ]
        );

        return redirect()
            ->route('admin.system.tenancy.edit')
            ->with('success', 'Tenant scoping setting saved.');
    }
}
