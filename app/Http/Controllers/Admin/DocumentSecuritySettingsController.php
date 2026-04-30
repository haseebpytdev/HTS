<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentSecuritySettingsRequest;
use App\Services\Documents\DocumentSecuritySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DocumentSecuritySettingsController extends Controller
{
    public function __construct(
        private readonly DocumentSecuritySettingsService $settings,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('manage-tenancy-settings');

        return view('admin.documents.security-settings', [
            'settings' => $this->settings->policy(),
        ]);
    }

    public function update(UpdateDocumentSecuritySettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tenancy-settings');
        $this->settings->savePolicy($request->validated());

        return redirect()->route('admin.documents.security-settings.edit')
            ->with('success', 'Document security settings updated.');
    }
}
