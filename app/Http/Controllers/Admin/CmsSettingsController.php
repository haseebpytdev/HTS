<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCmsSettingsRequest;
use App\Services\Content\ContentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CmsSettingsController extends Controller
{
    public function __construct(
        private readonly ContentSettingsService $settings,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.cms.settings', [
            'settings' => $this->settings->frontendSettings(),
        ]);
    }

    public function update(UpdateCmsSettingsRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->settings->saveFrontendSettings($request->validated());

        return redirect()->route('admin.cms.settings.edit')->with('success', 'CMS control panel settings updated.');
    }
}
