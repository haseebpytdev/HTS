<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIntegrationSupplierAccountRequest;
use App\Http\Requests\Admin\UpdateIntegrationSupplierAccountRequest;
use App\Models\IntegrationConnection;
use App\Models\Tenant;
use App\Services\Integrations\IntegrationConnectionAdminAuditService;
use App\Services\Integrations\IntegrationConnectionTestService;
use App\Services\Integrations\IntegrationSupplierAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IntegrationSupplierAccountController extends Controller
{
    public function __construct(
        private readonly IntegrationConnectionAdminAuditService $adminAudit,
    ) {
    }

    public function index(): View
    {
        $accounts = IntegrationConnection::query()
            ->with(['tenant', 'ownershipTenant'])
            ->orderByDesc('updated_at')
            ->when(request('provider'), fn ($q) => $q->where('provider', (string) request('provider')))
            ->get()
            ->groupBy(fn (IntegrationConnection $c): string => $c->account_key ?? ('legacy-'.$c->id));

        return view('admin.integrations.accounts.index', [
            'accounts' => $accounts,
            'providerFilter' => request('provider'),
        ]);
    }

    public function create(): View
    {
        return view('admin.integrations.accounts.create', [
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreIntegrationSupplierAccountRequest $request, IntegrationSupplierAccountService $service): RedirectResponse
    {
        $accountKey = $service->create($request->validated());
        $first = $service->getAccountConnections($accountKey)->first();
        if ($first !== null) {
            $this->adminAudit->logSupplierAccount('created', (string) $first->provider, $first->tenant_id, $accountKey, Auth::id());
        }

        return redirect()
            ->route('admin.integrations.accounts.edit', $accountKey)
            ->with('success', 'Supplier account created.');
    }

    public function edit(string $accountKey, IntegrationSupplierAccountService $service): View
    {
        $connections = $service->getAccountConnections($accountKey);
        abort_if($connections->isEmpty(), 404);

        $first = $connections->first();
        $test = $connections->firstWhere('environment', 'test');
        $production = $connections->firstWhere('environment', 'production');

        return view('admin.integrations.accounts.edit', [
            'accountKey' => $accountKey,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'connectionStatus' => [
                'test' => $test,
                'production' => $production,
            ],
            'form' => [
                'name' => $first?->name,
                'tenant_id' => $first?->tenant_id,
                'ownership_type' => $first?->ownership_type ?? 'tenant',
                'ownership_tenant_id' => $first?->ownership_tenant_id,
                'provider' => $first?->provider,
                'default_environment' => $connections->firstWhere('is_default', true)?->environment,
                'test' => [
                    'base_url' => $test?->base_url,
                    'is_active' => (bool) ($test?->is_active ?? false),
                    'has_client_id' => $test?->credentials->contains('key_name', 'client_id') ?? false,
                    'has_client_secret' => $test?->credentials->contains('key_name', 'client_secret') ?? false,
                ],
                'production' => [
                    'base_url' => $production?->base_url,
                    'is_active' => (bool) ($production?->is_active ?? false),
                    'has_client_id' => $production?->credentials->contains('key_name', 'client_id') ?? false,
                    'has_client_secret' => $production?->credentials->contains('key_name', 'client_secret') ?? false,
                ],
            ],
        ]);
    }

    public function update(string $accountKey, UpdateIntegrationSupplierAccountRequest $request, IntegrationSupplierAccountService $service): RedirectResponse
    {
        $service->upsertByAccountKey($accountKey, $request->validated());
        $first = $service->getAccountConnections($accountKey)->first();
        if ($first !== null) {
            $this->adminAudit->logSupplierAccount('updated', (string) $first->provider, $first->tenant_id, $accountKey, Auth::id());
        }

        return redirect()
            ->route('admin.integrations.accounts.edit', $accountKey)
            ->with('success', 'Supplier account updated.');
    }

    public function show(IntegrationConnection $connection): View
    {
        $connection->load(['tenant', 'ownershipTenant', 'credentials']);

        return view('admin.integrations.accounts.show', [
            'connection' => $connection,
        ]);
    }

    public function destroy(string $accountKey, IntegrationSupplierAccountService $service): RedirectResponse
    {
        $connections = $service->getAccountConnections($accountKey);
        $first = $connections->first();
        if ($first !== null) {
            $this->adminAudit->logSupplierAccount('deleted', (string) $first->provider, $first->tenant_id, $accountKey, Auth::id(), [
                'integration_connection_ids' => $connections->pluck('id')->all(),
            ]);
        }
        $service->deleteByAccountKey($accountKey);

        return redirect()
            ->route('admin.integrations.accounts.index')
            ->with('success', 'Supplier account deleted.');
    }

    public function setDefault(Request $request, IntegrationSupplierAccountService $service): RedirectResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:integration_connections,id'],
        ]);

        $connection = IntegrationConnection::query()->findOrFail((int) $validated['connection_id']);
        $service->setDefaultConnection($connection);
        $this->adminAudit->logConnectionAction($connection, 'set_default', Auth::id(), ['via' => 'supplier_accounts']);

        return redirect()
            ->route('admin.integrations.accounts.index')
            ->with('success', 'Default provider updated.');
    }

    public function testConnection(Request $request, IntegrationConnectionTestService $tester): RedirectResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:integration_connections,id'],
        ]);

        $connection = IntegrationConnection::query()->findOrFail((int) $validated['connection_id']);
        $result = $tester->test($connection);
        $connection->refresh();
        $this->adminAudit->logConnectionAction($connection, 'tested', Auth::id(), [
            'test_ok' => $result['ok'],
            'message' => $result['message'],
            'via' => 'supplier_accounts',
        ]);

        return redirect()->back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }
}
