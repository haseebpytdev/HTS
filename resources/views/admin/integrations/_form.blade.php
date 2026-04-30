@php
    $isCreate = $isCreate ?? false;
    $action = $isCreate ? route('admin.integrations.store') : route('admin.integrations.update', $connection);
    $provider = \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::normalize((string) old('provider', $connection->provider));
    $ops = old('supported_operations', $connection->supported_operations ?? []);
    $isAmadeus = \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches($provider);
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $action }}">
            @csrf
            @unless($isCreate)
                @method('PUT')
            @endunless

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tenant/Company</label>
                    <select name="tenant_id" class="form-select">
                        <option value="">Platform (global)</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) old('tenant_id', $connection->tenant_id) === (string) $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Provider</label>
                    <select name="provider" id="provider_select" class="form-select" required>
                        <option value="travelport" @selected($provider === 'travelport')>TRAVELPORT</option>
                        <option value="sabre" @selected($provider === 'sabre')>SABRE</option>
                        <option value="{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE }}" @selected($provider === \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE)>AMADEUS SELF SERVICE</option>
                        <option value="iati" @selected($provider === 'iati')>IATI</option>
                        <option value="duffel" @selected($provider === 'duffel')>DUFFEL</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Environment</label>
                    <select name="environment" class="form-select" required>
                        @foreach(['sandbox', 'production'] as $env)
                            <option value="{{ $env }}" @selected(old('environment', $connection->environment ?? 'sandbox') === $env)>{{ $env === 'sandbox' ? 'Test (Sandbox)' : 'Production' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account name</label>
                    <input type="text" name="account_name" class="form-control" required value="{{ old('account_name', $connection->account_name ?? $connection->name) }}">
                    <div class="form-text">Use a descriptive label (for example: Finance team Duffel token owner).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Base URL</label>
                    <input type="url" name="base_url" class="form-control" value="{{ old('base_url', $connection->base_url) }}" placeholder="https://...">
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h6">Supported operations</h2>
            <p class="small text-muted mb-2">For Amadeus Self Service, keep Search, Pricing, and Booking enabled unless an operation is intentionally paused.</p>
            <div class="d-flex flex-wrap gap-3 mb-3">
                @foreach(['search' => 'Search', 'pricing' => 'Pricing', 'booking' => 'Booking'] as $opKey => $opLabel)
                    <label class="form-check-label d-flex align-items-center gap-2">
                        <input class="form-check-input integration-op-checkbox" type="checkbox" name="supported_operations[]" value="{{ $opKey }}" @checked(in_array($opKey, $ops, true))>
                        {{ $opLabel }}
                    </label>
                @endforeach
            </div>

            <hr class="my-4">
            <h2 class="h6">Pricing and tax defaults</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Base currency</label>
                    <input type="text" name="base_currency" class="form-control" maxlength="3" value="{{ old('base_currency', $configDefaults['base_currency'] ?? 'PKR') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax percent</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control" value="{{ old('tax_percent', $configDefaults['tax_percent'] ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Markup type</label>
                    <select name="markup_type" class="form-select">
                        <option value="percentage" @selected(old('markup_type', $configDefaults['markup_type'] ?? 'percentage') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('markup_type', $configDefaults['markup_type'] ?? 'percentage') === 'fixed')>Fixed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Markup value</label>
                    <input type="number" step="0.01" min="0" name="markup_value" class="form-control" value="{{ old('markup_value', $configDefaults['markup_value'] ?? 0) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Documentation URL</label>
                    <input type="url" name="documentation_url" class="form-control" value="{{ old('documentation_url', $configDefaults['documentation_url'] ?? '') }}" placeholder="https://...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Credential owner (optional)</label>
                    <input type="text" name="credential_owner" class="form-control" maxlength="255" value="{{ old('credential_owner', $configDefaults['credential_owner'] ?? '') }}" placeholder="Owner/team responsible for token rotation">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Module notes</label>
                    <input type="text" name="module_notes" class="form-control" value="{{ old('module_notes', $configDefaults['module_notes'] ?? '') }}" placeholder="Operational notes for this provider account">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked((bool) old('is_active', $connection->is_active ?? false))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" @checked((bool) old('is_default', $connection->is_default ?? false))>
                        <label for="is_default" class="form-check-label">Default</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <h2 class="h6">Credentials</h2>
            <p class="small text-muted">Saved values are never shown in the form. Leave blank to keep; enter only to set or replace.</p>
            @if($isAmadeus || $isCreate)
                <p class="small text-muted">Use separate connections for Amadeus test and production environments so credentials remain isolated per environment.</p>
            @endif
            <p class="small text-muted" data-provider-help="duffel" style="display: none;">
                Duffel test tokens start with <code>duffel_test_</code>. Keep test and live tokens in separate environment connections.
            </p>
            <p class="small text-muted" data-provider-help="sabre" style="display: none;">
                For Sabre, enter Developer Hub application <code>User ID</code> and <code>Password</code> in these two fields. Base URL must be host-only (for example <code>https://api.cert.platform.sabre.com</code>), token URL is fixed to <code>base_url + /v2/auth/token</code>, and BFM search URL is fixed to <code>base_url + /v5/offers/shop</code>.
            </p>

            @foreach($providerCredentialFields as $providerKey => $fields)
                <div class="provider-credential-group" data-provider-group="{{ $providerKey }}">
                    <div class="row g-3">
                        @foreach($fields as $field)
                            @php
                                $hasStored = in_array($field, $existingCredentialKeys ?? [], true)
                                    || ($providerKey === 'sabre' && $field === 'sabre_user_id' && in_array('client_id', $existingCredentialKeys ?? [], true))
                                    || ($providerKey === 'sabre' && $field === 'sabre_password' && in_array('client_secret', $existingCredentialKeys ?? [], true));
                                $isSecretish = str_contains($field, 'secret') || str_contains($field, 'password') || str_contains($field, 'api_key') || str_contains($field, 'api_token');
                                $storedPlaceholder = $isSecretish ? '••••••••  (enter to replace)' : '— stored — enter to replace';
                            @endphp
                            <div class="col-md-4">
                                @php
                                    $fieldLabel = strtoupper(str_replace('_', ' ', $field));
                                    if ($providerKey === 'sabre' && $field === 'sabre_user_id') {
                                        $fieldLabel = 'SABRE USER ID';
                                    } elseif ($providerKey === 'sabre' && $field === 'sabre_password') {
                                        $fieldLabel = 'SABRE PASSWORD';
                                    }
                                @endphp
                                <label class="form-label">{{ $fieldLabel }}</label>
                                @if($hasStored && ! $isCreate)
                                    <div class="small text-muted mb-1">Stored securely — not displayed</div>
                                @endif
                                <input
                                    type="{{ $isSecretish ? 'password' : 'text' }}"
                                    autocomplete="new-password"
                                    class="form-control"
                                    name="credentials[{{ $field }}]"
                                    value="{{ old('credentials.'.$field, '') }}"
                                    @if($field === 'api_token') autocapitalize="off" spellcheck="false" @endif
                                    placeholder="{{ $hasStored && ! $isCreate ? $storedPlaceholder : '' }}"
                                    data-credential-field="{{ $field }}"
                                >
                            </div>
                        @endforeach
                    </div>
                    <hr class="my-3">
                </div>
            @endforeach

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">{{ $isCreate ? 'Create Connection' : 'Save Changes' }}</button>
                <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const providerSelect = document.getElementById('provider_select');
        if (!providerSelect) return;
        const opCheckboxes = Array.from(document.querySelectorAll('.integration-op-checkbox'));
        const duffelProvider = 'duffel';

        const toggleGroups = function () {
            const selected = providerSelect.value;
            document.querySelectorAll('[data-provider-group]').forEach(function (el) {
                el.style.display = el.getAttribute('data-provider-group') === selected ? '' : 'none';
            });
            document.querySelectorAll('[data-provider-help]').forEach(function (el) {
                el.style.display = el.getAttribute('data-provider-help') === selected ? '' : 'none';
            });

            const amadeusProvider = @json(\App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE);
            const amadeusFields = document.querySelectorAll('[data-provider-group="' + amadeusProvider + '"] [data-credential-field]');
            amadeusFields.forEach(function (input) {
                const field = input.getAttribute('data-credential-field');
                const shouldRequire = selected === amadeusProvider && (field === 'client_id' || field === 'client_secret');
                input.required = shouldRequire;
            });
            const sabreFields = document.querySelectorAll('[data-provider-group="sabre"] [data-credential-field]');
            sabreFields.forEach(function (input) {
                const field = input.getAttribute('data-credential-field');
                const shouldRequire = selected === 'sabre' && (field === 'sabre_user_id' || field === 'sabre_password');
                input.required = shouldRequire;
            });
            const duffelFields = document.querySelectorAll('[data-provider-group="' + duffelProvider + '"] [data-credential-field]');
            duffelFields.forEach(function (input) {
                const field = input.getAttribute('data-credential-field');
                input.required = selected === duffelProvider && field === 'api_token';
            });

            if (selected === amadeusProvider) {
                const anyChecked = opCheckboxes.some(function (checkbox) {
                    return checkbox.checked;
                });
                if (!anyChecked) {
                    opCheckboxes.forEach(function (checkbox) {
                        checkbox.checked = true;
                    });
                }
            }
        };

        providerSelect.addEventListener('change', toggleGroups);
        toggleGroups();
    })();
</script>
