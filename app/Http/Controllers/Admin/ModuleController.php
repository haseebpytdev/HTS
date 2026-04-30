<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterModuleIndexRequest;
use App\Http\Requests\Admin\UpdateModuleConfigurationRequest;
use App\Http\Requests\Admin\UpdateModuleStatusRequest;
use App\Models\ServiceModule;
use App\Models\ServiceModuleCredential;
use App\Models\ServiceModuleHealthCheck;
use App\Models\ServiceModulePricingRule;
use App\Models\ServiceModuleTaxRule;
use App\Services\Modules\ModuleCatalogService;
use App\Services\Modules\ModuleConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleCatalogService $catalog,
        private readonly ModuleConfigurationService $configuration,
    ) {
    }

    public function index(FilterModuleIndexRequest $request): View
    {
        $validated = $request->validated();
        $serviceType = (string) ($validated['service_type'] ?? '');
        $serviceType = $serviceType !== '' ? $serviceType : null;
        $status = (string) ($validated['status'] ?? 'all');
        $status = $status !== '' ? $status : 'all';

        $modules = $this->catalog->listModules($serviceType);
        if ($status !== 'all') {
            $modules = array_values(array_filter($modules, static function (array $module) use ($status): bool {
                return match ($status) {
                    'active' => (bool) ($module['enabled'] ?? false) === true,
                    'inactive' => (bool) ($module['enabled'] ?? false) === false,
                    'misconfigured' => (string) ($module['connection_status'] ?? '') === 'warning'
                        || in_array((string) ($module['health_status'] ?? ''), ['warning', 'critical'], true),
                    'healthy', 'warning', 'critical' => (string) ($module['health_status'] ?? '') === $status,
                    default => true,
                };
            }));
        }

        $summary = [
            'total' => count($modules),
            'active' => count(array_filter($modules, static fn (array $m): bool => (bool) ($m['enabled'] ?? false) === true)),
            'inactive' => count(array_filter($modules, static fn (array $m): bool => (bool) ($m['enabled'] ?? false) === false)),
            'unhealthy' => count(array_filter($modules, static fn (array $m): bool => in_array((string) ($m['health_status'] ?? ''), ['warning', 'critical'], true))),
        ];

        return view('admin.modules.index', [
            'modules' => $modules,
            'serviceTypes' => $this->catalog->serviceTypes(),
            'activeServiceType' => $serviceType,
            'activeStatus' => $status,
            'summary' => $summary,
        ]);
    }

    public function updateStatus(UpdateModuleStatusRequest $request, string $module): RedirectResponse
    {
        $data = $request->validated();
        $this->configuration->updateStatus(
            moduleKey: $module,
            isActive: (bool) ($data['is_active'] ?? false),
            environment: (string) $data['environment'],
            isDefault: (bool) ($data['is_default_provider'] ?? false),
            operations: (array) ($data['available_operations'] ?? []),
            actorUserId: Auth::id()
        );

        if ((bool) ($data['is_active'] ?? false)) {
            try {
                $this->configuration->validateProviderSpecificRequirements($module, (string) $data['environment']);
            } catch (\RuntimeException $e) {
                return back()->with('warning', 'Module enabled with warning: '.$e->getMessage().' Status set to misconfigured.');
            }
        }

        return back()->with('success', 'Module status updated.');
    }

    public function edit(string $module): View
    {
        $row = ServiceModule::query()
            ->where('code', $module)
            ->orWhere('module_key', $module)
            ->firstOrFail();

        $pricing = ServiceModulePricingRule::query()->where('service_module_id', $row->id)->latest('id')->first();
        $tax = ServiceModuleTaxRule::query()->where('service_module_id', $row->id)->latest('id')->first();
        $health = ServiceModuleHealthCheck::query()->where('service_module_id', $row->id)->latest('checked_at')->first();
        $credentialKeysByEnv = [
            'sandbox' => ServiceModuleCredential::query()
                ->where('service_module_id', $row->id)
                ->where('credential_key', 'like', 'sandbox:%')
                ->pluck('credential_key')
                ->map(fn ($k) => str_replace('sandbox:', '', (string) $k))
                ->values()
                ->all(),
            'production' => ServiceModuleCredential::query()
                ->where('service_module_id', $row->id)
                ->where('credential_key', 'like', 'production:%')
                ->pluck('credential_key')
                ->map(fn ($k) => str_replace('production:', '', (string) $k))
                ->values()
                ->all(),
        ];

        return view('admin.modules.edit', [
            'module' => $row,
            'settings' => $pricing,
            'tax' => $tax,
            'health' => $health,
            'credentialKeysByEnv' => $credentialKeysByEnv,
            'credentialFields' => $this->credentialFieldsForProvider((string) ($row->provider ?: $row->provider_code)),
        ]);
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

    /**
     * @return array<int, string>
     */
    private function credentialFieldsForProvider(string $provider): array
    {
        $map = [
            'sabre' => ['client_id', 'client_secret', 'pcc', 'epr', 'username', 'password', 'api_key'],
            'travelport' => ['client_id', 'client_secret', 'branch_code', 'username', 'password', 'api_key'],
            'amadeus' => ['client_id', 'client_secret', 'username', 'password', 'api_key'],
            'amadeus_self_service' => ['client_id', 'client_secret', 'username', 'password', 'api_key'],
            'iati' => ['client_id', 'client_secret', 'username', 'password', 'api_key'],
            'duffel' => ['api_key', 'client_secret'],
            'internal' => ['api_key', 'username', 'password'],
        ];

        return $map[strtolower($provider)] ?? ['client_id', 'client_secret', 'username', 'password', 'api_key'];
    }
}
