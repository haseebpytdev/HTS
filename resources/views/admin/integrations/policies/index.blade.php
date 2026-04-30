@extends('layouts.admin')

@section('title', 'Tenant integration policies')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Tenant integration policies</h1>
        <a href="{{ route('admin.integrations.providers.index') }}" class="btn btn-outline-secondary">Providers</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Plan Tier</th>
                        <th>Providers</th>
                        <th>Permissions</th>
                        <th>Multi/Fallback</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($tenants as $tenant)
                        @php($policy = $tenant->integrationPolicy)
                        <tr>
                            <td>{{ $tenant->name }}</td>
                            <td><span class="badge bg-secondary">{{ strtoupper($tenant->plan_tier ?? 'basic') }}</span></td>
                            <td>{{ $policy ? implode(', ', $policy->allowed_providers ?? []) : 'Plan defaults' }}</td>
                            <td>
                                @if($policy)
                                    @php($perm = $policy->provider_permissions ?? [])
                                    <span class="badge {{ ($perm['search'] ?? false) ? 'bg-success' : 'bg-secondary' }}">Search</span>
                                    <span class="badge {{ ($perm['pricing'] ?? false) ? 'bg-success' : 'bg-secondary' }}">Pricing</span>
                                    <span class="badge {{ ($perm['booking'] ?? false) ? 'bg-success' : 'bg-secondary' }}">Booking</span>
                                @else
                                    <span class="text-muted">Plan defaults</span>
                                @endif
                            </td>
                            <td>
                                @if($policy)
                                    <span class="badge {{ $policy->allow_multi_provider ? 'bg-success' : 'bg-secondary' }}">Multi</span>
                                    <span class="badge {{ $policy->allow_fallback ? 'bg-success' : 'bg-secondary' }}">Fallback</span>
                                @else
                                    <span class="text-muted">Plan defaults</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.integrations.policies.edit', $tenant) }}" class="btn btn-sm btn-outline-primary">Configure</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
