<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;

class BrandingSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $payload = (array) $request->validated('settings', []);
        $allowed = [
            'app.branding.primary_color' => $payload['app.branding.primary_color'] ?? null,
            'app.branding.logo_url' => $payload['app.branding.logo_url'] ?? null,
        ];
        $this->settings->setMany($allowed);

        return back()->with('success', 'Branding settings updated.');
    }
}
