<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantModuleAccessRequest;
use App\Http\Requests\Admin\UpdateTenantProviderAccessRequest;
use App\Models\Tenant;
use App\Services\Integrations\TenantProviderAccessService;
use App\Services\Tenancy\TenantPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantModuleAccessController extends Controller
{
    public function edit(Tenant $tenant, TenantPlanService $service): View
    {
        return view('admin.tenants.module-access-edit', [
            'tenant' => $tenant,
            'rows' => $service->moduleAccessMatrix($tenant),
        ]);
    }

    public function update(Tenant $tenant, UpdateTenantModuleAccessRequest $request, TenantPlanService $service): RedirectResponse
    {
        $service->saveModuleAccessMatrix($tenant, (array) $request->validated('rows', []));

        return redirect()
            ->route('admin.tenants.modules.edit', $tenant)
            ->with('success', 'Tenant module access updated.');
    }

    public function editProviders(Tenant $tenant, TenantProviderAccessService $service): View
    {
        return view('admin.tenants.provider-access-edit', [
            'tenant' => $tenant,
            'rows' => $service->matrixForTenant($tenant),
        ]);
    }

    public function updateProviders(
        Tenant $tenant,
        UpdateTenantProviderAccessRequest $request,
        TenantProviderAccessService $service
    ): RedirectResponse {
        $service->saveTenantOverrides($tenant, (array) $request->validated('rows', []));

        return redirect()
            ->route('admin.tenants.providers.edit', $tenant)
            ->with('success', 'Tenant provider access updated.');
    }
}
