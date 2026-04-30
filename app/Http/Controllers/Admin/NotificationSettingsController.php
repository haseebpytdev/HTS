<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $payload = (array) $request->validated('settings', []);
        $allowed = [
            'notifications.email_enabled' => $payload['notifications.email_enabled'] ?? null,
            'notifications.sms_enabled' => $payload['notifications.sms_enabled'] ?? null,
        ];
        $this->settings->setMany($allowed);

        return back()->with('success', 'Notification settings updated.');
    }
}
