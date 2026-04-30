<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantPlanRequest;
use App\Models\Tenant;
use App\Services\Tenancy\TenantPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantPlanController extends Controller
{
    public function index(TenantPlanService $service): View
    {
        $tenants = $service->listTenants();
        $governanceByTenant = [];
        foreach ($tenants as $tenant) {
            $governanceByTenant[$tenant->id] = $service->governanceSummary($tenant);
        }

        return view('admin.tenants.plans-index', [
            'tenants' => $tenants,
            'governanceByTenant' => $governanceByTenant,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.plan-edit', ['tenant' => $tenant]);
    }

    public function update(Tenant $tenant, UpdateTenantPlanRequest $request, TenantPlanService $service): RedirectResponse
    {
        $service->updateTenantPlan($tenant, $request->validated());

        return redirect()
            ->route('admin.tenants.plans.edit', $tenant)
            ->with('success', 'Tenant plan governance updated.');
    }
}
