<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantProviderAccessRequest;
use App\Models\Tenant;
use App\Services\Integrations\IntegrationConnectionAdminAuditService;
use App\Services\Integrations\TenantProviderAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantProviderAccessController extends Controller
{
    public function __construct(
        private readonly IntegrationConnectionAdminAuditService $adminAudit,
    ) {
    }

    public function index(TenantProviderAccessService $service): View
    {
        $tenants = Tenant::query()->orderBy('name')->get();
        $matrixByTenant = [];
        foreach ($tenants as $tenant) {
            $matrixByTenant[$tenant->id] = $service->matrixForTenant($tenant);
        }

        return view('admin.integrations.access-matrix', [
            'tenants' => $tenants,
            'matrixByTenant' => $matrixByTenant,
        ]);
    }

    public function update(Tenant $tenant, UpdateTenantProviderAccessRequest $request, TenantProviderAccessService $service): RedirectResponse
    {
        $validated = $request->validated();
        $service->saveTenantOverrides($tenant, $validated['rows']);

        $this->adminAudit->logAccessMatrix($tenant, Auth::id(), [
            'row_count' => count($validated['rows']),
        ]);

        return redirect()->route('admin.integrations.access-matrix.index')
            ->with('success', 'Tenant provider access updated for '.$tenant->name.'.');
    }
}
