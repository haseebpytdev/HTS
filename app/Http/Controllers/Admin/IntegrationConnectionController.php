<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterIntegrationConnectionRequest;
use App\Http\Requests\Admin\StoreIntegrationConnectionRequest;
use App\Http\Requests\Admin\UpdateIntegrationConnectionRequest;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\Tenant;
use App\Services\Integrations\IntegrationCredentialValidationService;
use App\Services\Integrations\IntegrationConnectionAdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegrationConnectionController extends Controller
{
    public function __construct(
        private readonly IntegrationConnectionAdminAuditService $adminAudit,
        private readonly IntegrationCredentialValidationService $credentialValidation,
    ) {
    }

    public function index(FilterIntegrationConnectionRequest $request): View
    {
        $filters = $request->validated();

        $query = IntegrationConnection::query()->with('tenant')->orderByDesc('updated_at');

        foreach (['provider', 'tenant_id', 'environment', 'status'] as $key) {
            if (! empty($filters[$key])) {
                if ($key === 'provider' && AmadeusSelfServiceProvider::matches((string) $filters[$key])) {
                    $query->whereIn('provider', AmadeusSelfServiceProvider::aliases());
                } elseif ($key === 'environment') {
                    $query->whereIn('environment', $this->environmentAliases((string) $filters[$key]));
                } else {
                    $query->where($key, $filters[$key]);
                }
            }
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $query->where('is_active', (bool) $filters['active']);
        }
        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where(function ($w) use ($q): void {
                $w->where('account_name', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            });
        }

        return view('admin.integrations.index', [
            'connections' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.integrations.create', $this->formData(new IntegrationConnection()));
    }

    public function store(StoreIntegrationConnectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $credentials = $this->normalizeCredentialsForProvider(
            provider: (string) ($data['provider'] ?? ''),
            credentials: $this->credentialValidation->sanitizeForPersistence((array) ($data['credentials'] ?? []))
        );

        $connection = new IntegrationConnection();
        $this->fillConnection($connection, $data);
        $connection->created_by = Auth::id();
        $connection->updated_by = Auth::id();
        $connection->save();

        $this->upsertCredentials($connection, $credentials);
        $this->applyDefault($connection, (bool) ($data['is_default'] ?? false));

        $this->adminAudit->logConnectionAction($connection, 'created', Auth::id());

        return redirect()->route('admin.integrations.edit', $connection)->with('success', 'Integration connection created.');
    }

    public function show(IntegrationConnection $integration): View
    {
        $integration->load(['tenant', 'credentials']);

        return view('admin.integrations.show', [
            'connection' => $integration,
        ]);
    }

    public function edit(IntegrationConnection $integration): View
    {
        $integration->load('credentials');

        return view('admin.integrations.edit', $this->formData($integration));
    }

    public function update(UpdateIntegrationConnectionRequest $request, IntegrationConnection $integration): RedirectResponse
    {
        $data = $request->validated();
        $credentials = $this->normalizeCredentialsForProvider(
            provider: (string) ($data['provider'] ?? ''),
            credentials: $this->credentialValidation->sanitizeForPersistence((array) ($data['credentials'] ?? []))
        );
        $this->fillConnection($integration, $data);
        $integration->updated_by = Auth::id();
        $integration->save();

        $this->upsertCredentials($integration, $credentials);
        $this->applyDefault($integration, (bool) ($data['is_default'] ?? false));

        $credentialKeysTouched = array_keys($credentials);
        $this->adminAudit->logConnectionAction($integration, 'updated', Auth::id(), [
            'credential_keys_updated' => $credentialKeysTouched,
        ]);

        return redirect()->route('admin.integrations.edit', $integration)->with('success', 'Integration connection updated.');
    }

    public function toggleActive(IntegrationConnection $integration): RedirectResponse
    {
        $nextActive = ! $integration->is_active;
        $integration->forceFill([
            'is_active' => $nextActive,
            'status' => $nextActive ? ($integration->status === 'disabled' ? 'untested' : $integration->status) : 'disabled',
            'updated_by' => Auth::id(),
        ])->save();

        $this->adminAudit->logConnectionAction($integration, $nextActive ? 'enabled' : 'disabled', Auth::id());

        return back()->with('success', $nextActive ? 'Connection enabled.' : 'Connection disabled.');
    }

    public function setDefault(IntegrationConnection $integration): RedirectResponse
    {
        IntegrationConnection::query()
            ->where('provider', $integration->provider)
            ->where('environment', $integration->environment)
            ->where(function ($q) use ($integration): void {
                if ($integration->tenant_id === null) {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', $integration->tenant_id);
                }
            })
            ->update(['is_default' => false]);

        $integration->forceFill(['is_default' => true, 'updated_by' => Auth::id()])->save();

        $this->adminAudit->logConnectionAction($integration, 'set_default', Auth::id());

        return back()->with('success', 'Default connection updated.');
    }

    public function destroy(IntegrationConnection $integration): RedirectResponse
    {
        $integration->forceFill([
            'updated_by' => Auth::id(),
            'is_default' => false,
            'is_active' => false,
            'status' => 'disabled',
        ])->save();
        $integration->delete();

        $this->adminAudit->logConnectionAction($integration, 'trashed', Auth::id());

        return back()->with('success', 'Connection moved to trash.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillConnection(IntegrationConnection $connection, array $data): void
    {
        $isActive = (bool) ($data['is_active'] ?? false);
        $supported = array_values(array_unique(array_map('strval', (array) ($data['supported_operations'] ?? []))));
        $provider = AmadeusSelfServiceProvider::normalize((string) ($data['provider'] ?? ''));
        if (AmadeusSelfServiceProvider::matches($provider) && $supported === []) {
            $supported = ['search', 'pricing', 'booking'];
        }

        $currentConfig = (array) ($connection->config ?? []);
        $providerFlags = AmadeusSelfServiceProvider::matches($provider)
            ? ['is_experimental' => true, 'provider_type' => 'sandbox']
            : ($provider === 'duffel' ? ['provider_type' => 'direct_booking_provider'] : []);

        $connection->fill([
            'tenant_id' => $data['tenant_id'] ?? null,
            'provider' => $provider,
            'environment' => (string) $data['environment'],
            'account_name' => (string) $data['account_name'],
            'name' => (string) $data['account_name'],
            'account_key' => $connection->account_key ?: (string) Str::uuid(),
            'base_url' => $data['base_url'] ?? null,
            'supported_operations' => $supported,
            'config' => array_merge($currentConfig, [
                'managed_via' => 'integration_connections_admin',
                'tax_percent' => isset($data['tax_percent']) ? (float) $data['tax_percent'] : $currentConfig['tax_percent'] ?? 0.0,
                'markup_type' => (string) ($data['markup_type'] ?? $currentConfig['markup_type'] ?? 'percentage'),
                'markup_value' => isset($data['markup_value']) ? (float) $data['markup_value'] : $currentConfig['markup_value'] ?? 0.0,
                'base_currency' => strtoupper((string) ($data['base_currency'] ?? $currentConfig['base_currency'] ?? 'PKR')),
                'documentation_url' => (string) ($data['documentation_url'] ?? $currentConfig['documentation_url'] ?? ''),
                'credential_owner' => (string) ($data['credential_owner'] ?? $currentConfig['credential_owner'] ?? ''),
                'module_notes' => (string) ($data['module_notes'] ?? $currentConfig['module_notes'] ?? ''),
            ], $providerFlags),
            'is_active' => $isActive,
            'status' => $isActive ? ($connection->status ?? 'untested') : 'disabled',
        ]);
    }

    /**
     * @param  array<string, string|null>  $credentials
     */
    private function upsertCredentials(IntegrationConnection $connection, array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            IntegrationCredential::query()->updateOrCreate(
                [
                    'integration_connection_id' => $connection->id,
                    'credential_key' => $key,
                ],
                [
                    'credential_value_encrypted' => $value,
                    'is_secret' => ! in_array($key, ['username', 'branch_code', 'pcc'], true),
                ]
            );
        }
    }

    private function applyDefault(IntegrationConnection $connection, bool $wantsDefault): void
    {
        if (! $wantsDefault) {
            return;
        }

        IntegrationConnection::query()
            ->where('provider', $connection->provider)
            ->where('environment', $connection->environment)
            ->where(function ($q) use ($connection): void {
                if ($connection->tenant_id === null) {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', $connection->tenant_id);
                }
            })
            ->update(['is_default' => false]);

        $connection->forceFill(['is_default' => true])->save();
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array<string, string>
     */
    private function normalizeCredentialsForProvider(string $provider, array $credentials): array
    {
        if (strtolower(AmadeusSelfServiceProvider::normalize($provider)) !== 'sabre') {
            return $credentials;
        }

        $sabreUserId = trim((string) ($credentials['sabre_user_id'] ?? ''));
        $sabrePassword = trim((string) ($credentials['sabre_password'] ?? ''));
        if ($sabreUserId !== '') {
            $credentials['client_id'] = $sabreUserId;
        }
        if ($sabrePassword !== '') {
            $credentials['client_secret'] = $sabrePassword;
        }

        unset($credentials['sabre_user_id'], $credentials['sabre_password']);

        return $credentials;
    }

    /**
     * @return list<string>
     */
    private function environmentAliases(string $environment): array
    {
        $normalized = strtolower(trim($environment));

        return match ($normalized) {
            'sandbox', 'test', 'testing', 'development' => ['sandbox', 'test', 'testing', 'development'],
            'production', 'live' => ['production', 'live'],
            default => [$normalized],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(IntegrationConnection $connection): array
    {
        $providerCredentialFields = [
            'travelport' => ['client_id', 'client_secret', 'username', 'password', 'pcc', 'branch_code'],
            'sabre' => ['sabre_user_id', 'sabre_password'],
            AmadeusSelfServiceProvider::CODE => ['client_id', 'client_secret'],
            'iati' => ['client_id', 'client_secret', 'api_key'],
            'duffel' => ['api_token'],
        ];

        $existingKeys = $connection->exists
            ? $connection->credentials->pluck('credential_key')->filter()->values()->all()
            : [];

        return [
            'connection' => $connection,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'providerCredentialFields' => $providerCredentialFields,
            'existingCredentialKeys' => $existingKeys,
            'configDefaults' => [
                'tax_percent' => (float) (($connection->config['tax_percent'] ?? 0)),
                'markup_type' => (string) (($connection->config['markup_type'] ?? 'percentage')),
                'markup_value' => (float) (($connection->config['markup_value'] ?? 0)),
                'base_currency' => (string) (($connection->config['base_currency'] ?? 'PKR')),
                'documentation_url' => (string) (($connection->config['documentation_url'] ?? '')),
                'credential_owner' => (string) (($connection->config['credential_owner'] ?? '')),
                'module_notes' => (string) (($connection->config['module_notes'] ?? '')),
            ],
        ];
    }
}
