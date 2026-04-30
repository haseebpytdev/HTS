@php
    $editing = isset($accountKey);
    $action = $editing ? route('admin.integrations.accounts.update', $accountKey) : route('admin.integrations.accounts.store');
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Account/Company label</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $form['name'] ?? '') }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Provider</label>
            <select name="provider" id="supplier_account_provider_select" class="form-select" required>
                @php($selectedProvider = \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::normalize((string) old('provider', $form['provider'] ?? '')))
                @foreach([
                    'travelport' => 'TRAVELPORT',
                    'sabre' => 'SABRE',
                    \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE => 'AMADEUS SELF SERVICE',
                    'iati' => 'IATI',
                    'duffel' => 'DUFFEL',
                ] as $provider => $label)
                    <option value="{{ $provider }}" @selected($selectedProvider === $provider)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Credential ownership</label>
            <select name="ownership_type" class="form-select" required>
                @foreach(['platform_owner' => 'Platform owner', 'tenant' => 'Tenant/company', 'shared_enterprise' => 'Shared enterprise account'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('ownership_type', $form['ownership_type'] ?? 'tenant') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Owning tenant/company (if tenant-owned)</label>
            <select name="ownership_tenant_id" class="form-select">
                <option value="">N/A</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string) old('ownership_tenant_id', $form['ownership_tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Legacy tenant field</label>
            <select name="tenant_id" class="form-select">
                <option value="">Global (no tenant)</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string) old('tenant_id', $form['tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <hr class="my-4">
    <h2 class="h6"><span class="badge bg-info text-dark me-2">Sandbox</span> credentials</h2>
    <p class="small text-muted">Client ID and secret are never shown after save. Empty field + bullet placeholder means a value is stored; enter only to replace.</p>
    <p class="small text-muted" data-provider-help="sabre" style="display: none;">
        Sabre uses OAuth <code>client_credentials</code> only. Enter Dev Hub app <code>client_id</code> and <code>client_secret</code>; user/password are not used for runtime token requests. Keep base URL host-only, for example <code>https://api.cert.platform.sabre.com</code>.
    </p>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Sandbox base URL</label>
            <input type="url" name="test[base_url]" class="form-control" value="{{ old('test.base_url', $form['test']['base_url'] ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Client ID</label>
            <input type="password" autocomplete="new-password" name="test[client_id]" class="form-control" value="{{ old('test.client_id', '') }}" placeholder="{{ !empty($form['test']['has_client_id']) ? '••••••••  (enter to replace)' : '' }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Client Secret</label>
            <input type="password" autocomplete="new-password" name="test[client_secret]" class="form-control" value="{{ old('test.client_secret', '') }}" placeholder="{{ !empty($form['test']['has_client_secret']) ? '••••••••  (enter to replace)' : '' }}">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="hidden" name="test[is_active]" value="0">
                <input type="checkbox" name="test[is_active]" value="1" class="form-check-input" id="test_active"
                    @checked((bool) old('test.is_active', $form['test']['is_active'] ?? false))>
                <label class="form-check-label" for="test_active">Active</label>
            </div>
        </div>
    </div>

    <hr class="my-4">
    <h2 class="h6"><span class="badge bg-danger me-2">Production</span> credentials</h2>
    <p class="small text-muted">Same masking rules as sandbox. Double-check you are editing the correct environment.</p>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Production base URL</label>
            <input type="url" name="production[base_url]" class="form-control" value="{{ old('production.base_url', $form['production']['base_url'] ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Client ID</label>
            <input type="password" autocomplete="new-password" name="production[client_id]" class="form-control" value="{{ old('production.client_id', '') }}" placeholder="{{ !empty($form['production']['has_client_id']) ? '••••••••  (enter to replace)' : '' }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Client Secret</label>
            <input type="password" autocomplete="new-password" name="production[client_secret]" class="form-control" value="{{ old('production.client_secret', '') }}" placeholder="{{ !empty($form['production']['has_client_secret']) ? '••••••••  (enter to replace)' : '' }}">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="hidden" name="production[is_active]" value="0">
                <input type="checkbox" name="production[is_active]" value="1" class="form-check-input" id="production_active"
                    @checked((bool) old('production.is_active', $form['production']['is_active'] ?? false))>
                <label class="form-check-label" for="production_active">Active</label>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <label class="form-label">Default provider environment</label>
        <select name="default_environment" class="form-select" style="max-width: 260px;">
            <option value="">No default</option>
            <option value="test" @selected(old('default_environment', $form['default_environment'] ?? '') === 'test')>Sandbox (test)</option>
            <option value="production" @selected(old('default_environment', $form['default_environment'] ?? '') === 'production')>Production</option>
        </select>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mt-3 mb-0">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create supplier account' }}</button>
        <a href="{{ route('admin.integrations.accounts.index') }}" class="btn btn-light">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const providerSelect = document.getElementById('supplier_account_provider_select');
        if (!providerSelect) return;

        const toggleProviderHelp = function () {
            const selected = providerSelect.value;
            document.querySelectorAll('[data-provider-help]').forEach(function (el) {
                el.style.display = el.getAttribute('data-provider-help') === selected ? '' : 'none';
            });
        };

        providerSelect.addEventListener('change', toggleProviderHelp);
        toggleProviderHelp();
    })();
</script>
