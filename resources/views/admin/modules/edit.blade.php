@extends('layouts.admin')

@section('title', 'Module Configuration')

@section('admin-content')
    @php($providerCode = (string) ($module->provider ?: $module->provider_code))
    @php($ops = (array) ($module->supported_operations_json ?: $module->available_operations ?: []))
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $module->name ?: $module->provider_name }} - Module Configuration</h1>
            <div class="small text-muted">{{ $module->service_type }} · {{ strtoupper($providerCode) }}</div>
        </div>
        <a href="{{ route('admin.modules.index') }}" class="btn btn-sm btn-outline-secondary">Back to Modules</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.modules.configuration.update', ['module' => $module->code ?: $module->module_key]) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6">A. API Credentials</h2>
                        <p class="small text-muted">Secrets are encrypted and never shown again after save. Leave blank to keep existing values.</p>
                        <div class="row g-3">
                            @foreach(['sandbox', 'production'] as $env)
                                <div class="col-lg-6">
                                    <div class="border rounded p-3">
                                        <div class="fw-semibold mb-2 text-capitalize">{{ $env }}</div>
                                        @foreach($credentialFields as $field)
                                            @php($stored = in_array($field, $credentialKeysByEnv[$env] ?? [], true))
                                            <label class="form-label small mt-1">{{ strtoupper(str_replace('_', ' ', $field)) }}</label>
                                            @if($stored)
                                                <div class="small text-muted">Stored securely (enter to replace)</div>
                                            @endif
                                            <input type="password" class="form-control form-control-sm" name="credentials[{{ $env }}][{{ $field }}]" value="">
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">Module Metadata</h2>
                        <div class="small text-muted">Provider</div>
                        <div class="fw-semibold mb-2">{{ $module->name ?: $module->provider_name }}</div>
                        <div class="small text-muted">Service type</div>
                        <div class="fw-semibold mb-2">{{ $module->service_type }}</div>
                        <div class="small text-muted">Environment</div>
                        <div class="fw-semibold mb-2 text-capitalize">{{ $module->environment }}</div>
                        <div class="small text-muted">Status</div>
                        <div class="fw-semibold mb-2 text-capitalize">{{ $module->status ?? 'inactive' }}</div>
                        <div class="small text-muted">Connection</div>
                        <div class="fw-semibold mb-2 text-capitalize">{{ $module->connection_status ?? 'disconnected' }}</div>
                        <div class="small text-muted">Operations</div>
                        <div class="fw-semibold">{{ $ops !== [] ? implode(', ', $ops) : 'None' }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">B. Module Status Controls</h2>
                        <input type="hidden" name="is_active" value="0">
                        <input type="hidden" name="is_default_provider" value="0">
                        <input type="hidden" name="allow_fallback" value="0">
                        <input type="hidden" name="allow_multi_provider" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) $module->is_active)>
                            <label for="is_active" class="form-check-label">Active</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_default_provider" value="1" id="is_default_provider" @checked((bool) ($module->is_default_provider ?? $module->is_default))>
                            <label for="is_default_provider" class="form-check-label">Default provider for this service type</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_fallback" value="1" id="allow_fallback" @checked((bool) ($module->allow_fallback ?? true))>
                            <label for="allow_fallback" class="form-check-label">Fallback allowed</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_multi_provider" value="1" id="allow_multi_provider" @checked((bool) ($module->allow_multi_provider ?? false))>
                            <label for="allow_multi_provider" class="form-check-label">Multi-provider allowed</label>
                        </div>
                        <label class="form-label small">Environment</label>
                        <select name="environment" class="form-select mb-2">
                            @foreach(['development', 'sandbox', 'production'] as $env)
                                <option value="{{ $env }}" @selected($module->environment === $env)>{{ ucfirst($env) }}</option>
                            @endforeach
                        </select>
                        <label class="form-label small">Provider priority (lower runs first)</label>
                        <input type="number" min="1" max="9999" name="provider_priority" class="form-control mb-2" value="{{ (int) ($module->provider_priority ?? $module->sort_order ?? 100) }}">
                        <label class="form-label small">Available operations</label>
                        <div class="d-flex gap-3">
                            @php($ops = (array) ($module->supported_operations_json ?: $module->available_operations ?: []))
                            @foreach(['search','pricing','booking'] as $op)
                                <label class="form-check-label d-flex align-items-center gap-1">
                                    <input class="form-check-input" type="checkbox" name="available_operations[]" value="{{ $op }}" @checked(in_array($op, $ops, true))>
                                    {{ ucfirst($op) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">C. API Testing</h2>
                        @php($status = (string) ($health?->result_status ?? 'warning'))
                        @php($badgeClass = $status === 'healthy' ? 'bg-success' : ($status === 'critical' ? 'bg-danger' : 'bg-warning text-dark'))
                        <div class="mb-2"><span class="badge {{ $badgeClass }}">Health: {{ ucfirst($status) }}</span></div>
                        <div class="small text-muted mb-2">Last tested: {{ $module->last_tested_at?->toDateTimeString() ?? 'Never' }}</div>
                        <div class="small text-muted mb-2">Last success: {{ $module->last_success_at?->toDateTimeString() ?? 'N/A' }}</div>
                        <div class="small text-muted mb-2">Last failure: {{ $module->last_failure_at?->toDateTimeString() ?? 'N/A' }}</div>
                        <div class="small text-muted mb-2">Last failure reason: {{ $module->last_failure_reason ?? 'N/A' }}</div>
                        <div class="small text-muted mb-2">Latest health message: {{ $health?->message ?? 'N/A' }}</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('module-test-connection-form').submit();">Test API Connection</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">D. Tax Configuration</h2>
                        <label class="form-label small">Tax type</label>
                        <select name="tax_type" class="form-select mb-2">
                            <option value="percentage" @selected(($tax?->tax_type ?? 'percentage') === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(($tax?->tax_type ?? 'percentage') === 'fixed')>Fixed</option>
                        </select>
                        <label class="form-label small">Tax value</label>
                        <input type="number" step="0.01" min="0" name="tax_value" class="form-control" value="{{ (float) ($tax?->tax_value ?? 0) }}">
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6">E. Pricing Configuration</h2>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small">B2B type</label>
                                <select name="b2b_markup_type" class="form-select">
                                    <option value="percentage" @selected(($settings?->markup_type_b2b ?? 'percentage') === 'percentage')>Percentage</option>
                                    <option value="fixed" @selected(($settings?->markup_type_b2b ?? 'percentage') === 'fixed')>Fixed</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">B2B value</label>
                                <input type="number" step="0.01" min="0" name="b2b_markup_value" class="form-control" value="{{ (float) ($settings?->markup_value_b2b ?? 0) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">B2C type</label>
                                <select name="b2c_markup_type" class="form-select">
                                    <option value="percentage" @selected(($settings?->markup_type_b2c ?? 'percentage') === 'percentage')>Percentage</option>
                                    <option value="fixed" @selected(($settings?->markup_type_b2c ?? 'percentage') === 'fixed')>Fixed</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">B2C value</label>
                                <input type="number" step="0.01" min="0" name="b2c_markup_value" class="form-control" value="{{ (float) ($settings?->markup_value_b2c ?? 0) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Base currency</label>
                                <input type="text" maxlength="3" name="base_currency" class="form-control" value="{{ $settings?->base_currency_code ?? 'PKR' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Min markup guard</label>
                                <input type="number" step="0.01" min="0" name="min_markup_guard" class="form-control" value="{{ $module->config['min_markup_guard'] ?? '' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Max discount guard</label>
                                <input type="number" step="0.01" min="0" name="max_discount_guard" class="form-control" value="{{ $module->config['max_discount_guard'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6">G. Tenant Access and Plan Restrictions</h2>
                        <div class="small text-muted mb-2">
                            Tenant-level assignment, plan restrictions, per-tenant priority, and fallback/multi-provider rules are managed in the provider access matrix.
                        </div>
                        @if(Route::has('admin.integrations.access-matrix.index'))
                            <a href="{{ route('admin.integrations.access-matrix.index') }}" class="btn btn-sm btn-outline-dark mb-3">
                                Manage Tenant Access Matrix
                            </a>
                        @endif
                        @if(Route::has('admin.tenants.plans.index'))
                            <a href="{{ route('admin.tenants.plans.index') }}" class="btn btn-sm btn-outline-dark mb-3">
                                Manage Plan Restrictions
                            </a>
                        @endif

                        <h2 class="h6">F. Documentation / Help</h2>
                        <div class="row g-2">
                            <div class="col-lg-4">
                                <label class="form-label small">Provider setup guide link</label>
                                <input type="url" class="form-control" name="setup_guide_url" value="{{ (string) (($module->config['setup_guide_url'] ?? '')) }}">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label small">Docs link</label>
                                <input type="url" class="form-control" name="documentation_url" value="{{ (string) ($module->documentation_url ?? '') }}">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label small">Environment notes</label>
                                <input type="text" class="form-control" name="environment_notes" value="{{ (string) (($module->config['environment_notes'] ?? '')) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Troubleshooting hints</label>
                                <textarea class="form-control" rows="2" name="troubleshooting_hints">{{ (string) (($module->config['troubleshooting_hints'] ?? '')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-3">
            <button class="btn btn-primary" type="submit">Save Module Configuration</button>
        </div>
    </form>

    <form id="module-test-connection-form" method="POST" action="{{ route('admin.modules.test-connection', ['module' => $module->code ?: $module->module_key]) }}" class="d-none">
        @csrf
    </form>
@endsection
