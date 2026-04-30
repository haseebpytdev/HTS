<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleCredentialRequest;
use App\Services\Modules\ModuleConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ModuleCredentialController extends Controller
{
    public function __construct(
        private readonly ModuleConfigurationService $configuration,
    ) {
    }

    public function update(UpdateModuleCredentialRequest $request, string $module): RedirectResponse
    {
        $this->configuration->updateCredentials($module, (array) $request->validated('credentials', []), Auth::id());

        return back()->with('success', 'Module credentials updated.');
    }
}
