@extends('layouts.admin')

@section('title', 'Modules Marketplace')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-0">Modules Marketplace</h1>
            <div class="text-muted small">Manage providers, pricing, status, and health from one catalog.</div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Active / Total</div>
            <div class="h5 mb-0">
                <span class="text-success">{{ (int) ($summary['active'] ?? 0) }}</span>
                <span class="text-muted">/</span>
                <span>{{ (int) ($summary['total'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body border-bottom py-2">
            <div class="row g-2">
                <div class="col-md-3">
                    <div class="small text-muted">Total Modules</div>
                    <div class="h6 mb-0">{{ (int) ($summary['total'] ?? 0) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Active</div>
                    <div class="h6 mb-0 text-success">{{ (int) ($summary['active'] ?? 0) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Inactive</div>
                    <div class="h6 mb-0 text-secondary">{{ (int) ($summary['inactive'] ?? 0) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Unhealthy</div>
                    <div class="h6 mb-0 text-danger">{{ (int) ($summary['unhealthy'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="card-body d-flex flex-wrap gap-2 py-2">
            <a href="{{ route('admin.modules.index') }}"
               class="btn btn-sm {{ empty($activeServiceType) ? 'btn-primary' : 'btn-outline-secondary' }}">All Services</a>
            @foreach($serviceTypes as $serviceType)
                <a href="{{ route('admin.modules.index', ['service_type' => $serviceType, 'status' => $activeStatus ?? 'all']) }}"
                   class="btn btn-sm {{ $activeServiceType === $serviceType ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $serviceType }}
                </a>
            @endforeach
        </div>
        <div class="card-body pt-0 d-flex flex-wrap gap-2">
            @php($statuses = ['all' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'misconfigured' => 'Misconfigured', 'healthy' => 'Healthy', 'warning' => 'Warning', 'critical' => 'Critical'])
            @foreach($statuses as $statusKey => $statusLabel)
                <a href="{{ route('admin.modules.index', ['service_type' => $activeServiceType, 'status' => $statusKey]) }}"
                   class="btn btn-sm {{ ($activeStatus ?? 'all') === $statusKey ? 'btn-dark' : 'btn-outline-dark' }}">
                    {{ $statusLabel }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="row g-3">
        @forelse($modules as $module)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                @if(!empty($module['provider_logo_url']))
                                    <img src="{{ $module['provider_logo_url'] }}" alt="{{ $module['provider_name'] }}" style="width:34px;height:34px;border-radius:6px;object-fit:cover;">
                                @else
                                    <div class="d-inline-flex align-items-center justify-content-center text-uppercase fw-bold bg-light border" style="width:34px;height:34px;border-radius:6px;">
                                        {{ substr((string) $module['provider_name'], 0, 2) }}
                                    </div>
                                @endif
                                <div>
                                    <h2 class="h6 mb-0">{{ $module['provider_name'] }}</h2>
                                    <div class="small text-muted">{{ $module['service_type'] }}</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                @if(!empty($module['is_primary_operational']))
                                    <span class="badge bg-primary">Primary Operational</span>
                                @endif
                                @php($statusKey = (string) ($module['status'] ?? ($module['enabled'] ? 'active' : 'inactive')))
                                @php($statusBadge = match($statusKey) {
                                    'active' => 'bg-success',
                                    'misconfigured' => 'bg-warning text-dark',
                                    'disabled', 'inactive' => 'bg-secondary',
                                    default => 'bg-dark',
                                })
                                <span class="badge {{ $statusBadge }}">
                                    Status: {{ ucfirst($statusKey) }}
                                </span>
                                <span class="badge {{ $module['enabled'] ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $module['enabled'] ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="badge {{ $module['environment'] === 'production' ? 'bg-danger' : 'bg-info text-dark' }}">
                                    {{ ucfirst($module['environment']) }}
                                </span>
                                <span class="badge {{ $module['health_status'] === 'healthy' ? 'bg-success' : ($module['health_status'] === 'critical' ? 'bg-danger' : 'bg-warning text-dark') }}">
                                    Health: {{ ucfirst($module['health_status']) }}
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.modules.status.update', $module['key']) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="environment" value="{{ $module['environment'] }}">
                            <input type="hidden" name="is_default_provider" value="{{ $module['is_default_provider'] ? 1 : 0 }}">
                            @foreach(($module['available_operations'] ?? []) as $operation)
                                <input type="hidden" name="available_operations[]" value="{{ $operation }}">
                            @endforeach
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small">State</label>
                                    <input type="hidden" name="is_active" value="0">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="enabled_{{ $module['key'] }}" @checked($module['enabled'])
                                               @cannot('action.integrations.manage') disabled @endcannot>
                                        <label class="form-check-label" for="enabled_{{ $module['key'] }}">Enabled</label>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="row g-2">
                                        <div class="col-md-12">
                                            <div class="small text-muted">Supported Operations</div>
                                            <div class="d-flex flex-wrap gap-1">
                                                @forelse(($module['available_operations'] ?? []) as $operation)
                                                    <span class="badge bg-light text-dark border">{{ strtoupper((string) $operation) }}</span>
                                                @empty
                                                    <span class="text-muted small">No operations configured</span>
                                                @endforelse
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">B2B Markup</div>
                                            <div class="fw-semibold">{{ strtoupper($module['b2b_markup_type']) }} {{ number_format((float) $module['b2b_markup_value'], 2) }}</div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">B2C Markup</div>
                                            <div class="fw-semibold">{{ strtoupper($module['b2c_markup_type']) }} {{ number_format((float) $module['b2c_markup_value'], 2) }}</div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">Base Currency</div>
                                            <div class="fw-semibold">{{ strtoupper($module['base_currency']) }}</div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">Tax</div>
                                            <div class="fw-semibold">{{ number_format((float) ($module['tax_percent'] ?? 0), 2) }}%</div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">Priority / Fallback</div>
                                            <div class="fw-semibold">
                                                #{{ (int) ($module['provider_priority'] ?? 100) }}
                                                · {{ ($module['allow_fallback'] ?? true) ? 'Fallback On' : 'Fallback Off' }}
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="small text-muted">Multi-provider</div>
                                            <div class="fw-semibold">{{ ($module['allow_multi_provider'] ?? false) ? 'Enabled' : 'Disabled' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="d-flex flex-wrap gap-2">
                                    @can('action.integrations.manage')
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Save Toggle</button>
                                    @endcan
                                    @can('module.settings.view')
                                        <a href="{{ route('admin.modules.edit', ['module' => $module['key']]) }}" class="btn btn-primary btn-sm">Settings</a>
                                    @endcan
                                    @can('action.integrations.manage')
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('test_{{ $module['key'] }}').submit();">Test</button>
                                    @endcan
                                    @if(!empty($module['documentation_url']))
                                        <a href="{{ $module['documentation_url'] }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Docs</a>
                                    @else
                                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Docs</button>
                                    @endif
                                    @if(!empty($module['setup_guide_url']))
                                        <a href="{{ $module['setup_guide_url'] }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Help</a>
                                    @endif
                                    @if(Route::has('admin.integrations.index'))
                                        @can('module.integrations.view')
                                            <a href="{{ route('admin.integrations.index', ['provider' => $module['provider_code']]) }}" class="btn btn-outline-dark btn-sm">Connections</a>
                                        @endcan
                                    @endif
                                    @if(Route::has('admin.integrations.access-matrix.index'))
                                        <a href="{{ route('admin.integrations.access-matrix.index') }}" class="btn btn-outline-dark btn-sm">Tenant Access</a>
                                    @endif
                                    @if(Route::has('admin.tenants.plans.index'))
                                        <a href="{{ route('admin.tenants.plans.index') }}" class="btn btn-outline-dark btn-sm">Plan Rules</a>
                                    @endif
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge {{ $module['connection_status'] === 'connected' ? 'bg-success' : ($module['connection_status'] === 'degraded' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        Connection: {{ ucfirst($module['connection_status']) }}
                                    </span>
                                    <span class="badge {{ $module['health_status'] === 'healthy' ? 'bg-success' : ($module['health_status'] === 'critical' ? 'bg-danger' : 'bg-warning text-dark') }}">
                                        Health: {{ ucfirst($module['health_status']) }}
                                    </span>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">Last tested: {{ $module['last_tested_at']?->diffForHumans() ?? 'Never' }}</div>
                            <div class="small text-muted">Last success: {{ $module['last_success_at']?->diffForHumans() ?? 'N/A' }}</div>
                            <div class="small text-muted">Last failure: {{ $module['last_failure_at']?->diffForHumans() ?? 'N/A' }}</div>
                            @if(!empty($module['last_failure_reason']))
                                <div class="small text-danger mt-1">Reason: {{ $module['last_failure_reason'] }}</div>
                            @endif
                        </form>
                        <form id="test_{{ $module['key'] }}" method="POST" action="{{ route('admin.modules.test-connection', ['module' => $module['key']]) }}" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">No modules found for current filters.</div>
            </div>
        @endforelse
    </div>
@endsection
