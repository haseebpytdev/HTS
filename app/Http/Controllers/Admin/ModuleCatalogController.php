<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleConfigurationRequest;
use App\Http\Requests\Admin\UpdateModuleCatalogRequest;
use App\Services\Modules\ModuleCatalogService;
use App\Services\Modules\ModuleConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModuleCatalogController extends Controller
{
    public function __construct(
        private readonly ModuleCatalogService $catalog,
        private readonly ModuleConfigurationService $configuration,
    ) {
    }

    public function index(Request $request): View
    {
        $serviceType = $request->string('service_type')->toString();
        $serviceType = $serviceType !== '' ? $serviceType : null;

        return view('admin.modules.index', [
            'modules' => $this->catalog->listModules($serviceType),
            'serviceTypes' => $this->catalog->serviceTypes(),
            'activeServiceType' => $serviceType,
        ]);
    }

    public function update(UpdateModuleCatalogRequest $request, string $module): RedirectResponse
    {
        $this->catalog->updateModule($module, $request->validated());

        return redirect()
            ->route('admin.modules.index')
            ->with('success', 'Module configuration updated.');
    }

    public function edit(string $module): View
    {
        return view('admin.modules.edit', $this->catalog->getModuleConfiguration($module));
    }

    public function updateConfiguration(UpdateModuleConfigurationRequest $request, string $module): RedirectResponse
    {
        $this->catalog->updateModuleConfiguration($module, $request->validated());

        return redirect()
            ->route('admin.modules.edit', ['module' => $module])
            ->with('success', 'Module settings saved.');
    }

    public function testConnection(string $module): RedirectResponse
    {
        $result = $this->configuration->testConnection($module, Auth::id());

        return redirect()
            ->route('admin.modules.edit', ['module' => $module])
            ->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }
}
