@extends('layouts.admin')

@section('title', 'Integration Connections')

@section('admin-content')
    <x-ui.section-header
        title="Provider Connections"
        subtitle="Manage supplier modules, credentials, environment state, and default runtime routing."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.create') }}" class="btn btn-primary">Create Connection</a>
        </x-slot:actions>
    </x-ui.section-header>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $tabQuery = request()->except(['environment', 'page']);
        $envFilter = $filters['environment'] ?? request('environment');
        $normalizedEnvFilter = in_array($envFilter, ['sandbox', 'test', 'testing', 'development'], true)
            ? 'sandbox'
            : (in_array($envFilter, ['production', 'live'], true) ? 'production' : '');
    @endphp
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $normalizedEnvFilter === '' ? 'active' : '' }}" href="{{ route('admin.integrations.index', $tabQuery) }}">All environments</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $normalizedEnvFilter === 'sandbox' ? 'active' : '' }}" href="{{ route('admin.integrations.index', array_merge($tabQuery, ['environment' => 'sandbox'])) }}"><span class="badge bg-info text-dark me-1">Test</span> only</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $normalizedEnvFilter === 'production' ? 'active' : '' }}" href="{{ route('admin.integrations.index', array_merge($tabQuery, ['environment' => 'production'])) }}"><span class="badge bg-danger me-1">Production</span> only</a>
        </li>
    </ul>

    <form method="GET" action="{{ route('admin.integrations.index') }}" class="card card-body border-0 shadow-sm mb-3 integration-console-filter">
        <div class="row g-2">
            <div class="col-md-2">
                <select name="provider" class="form-select">
                    <option value="">Provider</option>
                    <option value="travelport" @selected(($filters['provider'] ?? '') === 'travelport')>TRAVELPORT</option>
                    <option value="sabre" @selected(($filters['provider'] ?? '') === 'sabre')>SABRE</option>
                    <option value="{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE }}" @selected(in_array(($filters['provider'] ?? ''), \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::aliases(), true))>AMADEUS SELF SERVICE</option>
                    <option value="iati" @selected(($filters['provider'] ?? '') === 'iati')>IATI</option>
                    <option value="duffel" @selected(($filters['provider'] ?? '') === 'duffel')>DUFFEL</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="environment" class="form-select">
                    <option value="">Environment</option>
                    @foreach(['sandbox' => 'Test', 'production' => 'Production'] as $env => $label)
                        <option value="{{ $env }}" @selected(($filters['environment'] ?? '') === $env)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Status</option>
                    @foreach(['untested','healthy','failed','disabled'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="tenant_id" class="form-select">
                    <option value="">Tenant/Company</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((string) ($filters['tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Account name">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm integration-console-table">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle as-table">
                    <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Tenant/Company</th>
                        <th>Environment</th>
                        <th>Account Name</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th>Active</th>
                        <th>Last tested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($connections as $connection)
                        @php
                            $normalizedEnvironment = in_array((string) $connection->environment, ['production', 'live'], true)
                                ? 'production'
                                : 'sandbox';
                            $statusClass = match($connection->status) {
                                'healthy' => 'bg-success',
                                'failed' => 'bg-danger',
                                'disabled' => 'bg-secondary',
                                default => 'bg-warning text-dark',
                            };
                            $envBadge = $normalizedEnvironment === 'production'
                                ? 'bg-danger'
                                : 'bg-info text-dark';
                            $rowEnvClass = $normalizedEnvironment === 'production'
                                ? 'border-start border-danger border-3'
                                : 'border-start border-info border-3';
                        @endphp
                        <tr class="{{ $rowEnvClass }}">
                            <td class="text-uppercase">{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $connection->provider) ? 'AMADEUS SELF SERVICE' : strtoupper((string) $connection->provider) }}</td>
                            <td>
                                <div>{{ $connection->tenant?->name ?? 'Platform' }}</div>
                                <div class="small text-muted">Tenant ID: {{ $connection->tenant_id ?? 'Platform' }}</div>
                            </td>
                            <td><x-ui.badge :tone="$normalizedEnvironment === 'production' ? 'danger' : 'info'">{{ $normalizedEnvironment === 'production' ? 'Production' : 'Sandbox' }}</x-ui.badge></td>
                            <td>{{ $connection->account_name ?? $connection->name }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ ucfirst($connection->status ?? 'untested') }}</span></td>
                            <td>{!! $connection->is_default ? '<span class="badge bg-info text-dark">Default</span>' : '<span class="text-muted">No</span>' !!}</td>
                            <td>{!! $connection->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>' !!}</td>
                            <td>{{ $connection->last_tested_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1 action-cluster">
                                    <a href="{{ route('admin.integrations.show', $connection) }}" class="btn btn-sm btn-outline-dark">View</a>
                                    <a href="{{ route('admin.integrations.edit', $connection) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.integrations.test', $connection) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Test</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.integrations.toggle', $connection) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm {{ $connection->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">
                                            {{ $connection->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.integrations.default', $connection) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-info" type="submit">Set default</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.integrations.destroy', $connection) }}" class="d-inline" onsubmit="return confirm('Move this connection to trash?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4">
                                <x-ui.empty-state
                                    title="No connections found"
                                    message="Try a different filter, or create a new provider connection to enable supplier traffic."
                                    icon="bi-plug"
                                />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $connections->links() }}</div>
        </div>
    </div>
@endsection
