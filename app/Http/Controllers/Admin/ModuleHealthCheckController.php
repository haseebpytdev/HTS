<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ModuleHealthCheckController extends Controller
{
    public function __construct(
        private readonly ModuleConfigurationService $configuration,
    ) {
    }

    public function store(string $module): RedirectResponse
    {
        $result = $this->configuration->testConnection($module, Auth::id());

        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }
}
