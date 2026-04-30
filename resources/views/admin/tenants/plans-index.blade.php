@extends('layouts.admin')

@section('title', 'Tenant Governance')

@section('admin-content')
    <h1 class="h4 mb-2">Tenant Governance</h1>
    <p class="text-muted mb-3">Manage service plans, module access, provider access, operation scopes, and usage limits.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th>Enabled Modules / Providers</th>
                    <th>Soft/Hard Limits</th>
                    <th>Overage Alerts</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tenants as $tenant)
                    <tr>
                        <td>{{ $tenant->name }}</td>
                        <td class="text-uppercase">{{ $tenant->plan_tier ?: 'basic' }}</td>
                        <td>
                            @php($gov = $governanceByTenant[$tenant->id] ?? ['enabled_modules' => 0, 'enabled_providers' => 0])
                            {{ (int) ($gov['enabled_modules'] ?? 0) }} / {{ (int) ($gov['enabled_providers'] ?? 0) }}
                        </td>
                        <td>{{ (int) ($tenant->soft_limit_percent ?? 80) }}% / {{ (bool) ($tenant->hard_limit_enforced ?? false) ? 'Hard' : 'Soft' }}</td>
                        <td>{{ (bool) ($tenant->overage_alert_enabled ?? true) ? 'Enabled' : 'Disabled' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.tenants.plans.edit', $tenant) }}" class="btn btn-sm btn-outline-primary">Plan</a>
                            <a href="{{ route('admin.tenants.modules.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">Modules</a>
                            <a href="{{ route('admin.tenants.providers.edit', $tenant) }}" class="btn btn-sm btn-outline-dark">Providers</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
