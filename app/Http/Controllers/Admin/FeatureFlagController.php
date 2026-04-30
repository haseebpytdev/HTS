<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $payload = (array) $request->validated('settings', []);
        $allowed = [
            'app.feature_flags.quote_auto_save' => $payload['app.feature_flags.quote_auto_save'] ?? null,
            'app.feature_flags.require_booking_approval' => $payload['app.feature_flags.require_booking_approval'] ?? null,
            'app.feature_flags.group_ticketing_enabled' => $payload['app.feature_flags.group_ticketing_enabled'] ?? null,
            'app.feature_flags.umrah_packages_enabled' => $payload['app.feature_flags.umrah_packages_enabled'] ?? null,
            'system.maintenance_enabled' => $payload['system.maintenance_enabled'] ?? null,
        ];
        $this->settings->setMany($allowed);

        return back()->with('success', 'Feature flags updated.');
    }
}
