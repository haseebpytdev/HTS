<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTenantIntegrationPolicyRequest;
use App\Models\Tenant;
use App\Models\TenantIntegrationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantIntegrationPolicyController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()
            ->with('integrationPolicy')
            ->orderBy('name')
            ->get();

        return view('admin.integrations.policies.index', [
            'tenants' => $tenants,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $policy = $tenant->integrationPolicy;

        return view('admin.integrations.policies.edit', [
            'tenant' => $tenant,
            'policy' => $policy,
        ]);
    }

    public function update(Tenant $tenant, UpdateTenantIntegrationPolicyRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $priority = array_values(array_filter(array_map(
            static fn (string $p): string => strtolower(trim($p)),
            explode(',', (string) ($v['provider_priority'] ?? ''))
        )));

        TenantIntegrationPolicy::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'allowed_providers' => array_values($v['allowed_providers'] ?? []),
                'provider_permissions' => [
                    'search' => (bool) ($v['search_enabled'] ?? false),
                    'pricing' => (bool) ($v['pricing_enabled'] ?? false),
                    'booking' => (bool) ($v['booking_enabled'] ?? false),
                ],
                'allow_multi_provider' => (bool) ($v['allow_multi_provider'] ?? false),
                'allow_fallback' => (bool) ($v['allow_fallback'] ?? false),
                'provider_priority' => $priority,
            ]
        );

        return redirect()
            ->route('admin.integrations.policies.edit', $tenant)
            ->with('success', 'Tenant integration policy saved.');
    }
}
