<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->settings->setMany((array) $request->validated('settings', []));

        return back()->with('success', 'System settings updated.');
    }
}
