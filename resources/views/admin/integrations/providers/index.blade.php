@extends('layouts.admin')

@section('title', 'Integrations Providers')

@section('admin-content')
    <x-ui.section-header
        title="Provider Modules"
        subtitle="Monitor module health, environment, and supported operations for each provider."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.accounts.index') }}" class="btn btn-outline-primary">Manage Connections</a>
        </x-slot:actions>
    </x-ui.section-header>

    <div class="row g-3">
        @foreach($providers as $row)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 integration-provider-card">
                    <div class="card-body">
                        @php($module = $row['module'])
                        @php($displayName = $module?->name ?: strtoupper((string) $row['provider']))
                        @php($statusKey = (string) ($row['module_status'] ?? 'inactive'))
                        @php($statusBadge = match($statusKey) {
                            'active' => 'bg-success',
                            'misconfigured' => 'bg-warning text-dark',
                            'disabled', 'inactive' => 'bg-secondary',
                            default => 'bg-dark',
                        })
                        @php($healthKey = (string) ($row['module_health_status'] ?? 'warning'))
                        @php($healthBadge = match($healthKey) {
                            'healthy' => 'bg-success',
                            'critical' => 'bg-danger',
                            default => 'bg-warning text-dark',
                        })

                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 mb-0">{{ $displayName }}</h2>
                            @if(\App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $row['provider']))
                                <x-ui.badge tone="primary">Primary Operational</x-ui.badge>
                            @endif
                        </div>
                        <div class="small text-uppercase text-muted mb-2">{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $row['provider']) ? 'amadeus_self_service' : $row['provider'] }}</div>
                        <div class="small text-muted mb-2">Connections: {{ $row['connections'] }}</div>
                        <div class="small text-muted mb-2">Active: {{ $row['active_connections'] }}</div>
                        <div class="small text-muted mb-2">
                            Environment:
                            <x-ui.badge :tone="($row['module_environment'] ?? 'sandbox') === 'production' ? 'danger' : 'info'">{{ ucfirst((string) ($row['module_environment'] ?? 'sandbox')) }}</x-ui.badge>
                        </div>
                        <div class="small text-muted mb-2">
                            Status: <span class="badge {{ $statusBadge }}">{{ ucfirst($statusKey) }}</span>
                        </div>
                        <div class="small text-muted mb-2">
                            Health: <span class="badge {{ $healthBadge }}">{{ ucfirst($healthKey) }}</span>
                        </div>
                        <div class="small text-muted mb-2">Supported operations:</div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @forelse(($row['supported_operations'] ?? []) as $operation)
                                <x-ui.badge tone="muted">{{ strtoupper((string) $operation) }}</x-ui.badge>
                            @empty
                                <span class="text-muted small">No operations configured</span>
                            @endforelse
                        </div>
                        <div class="small text-muted mb-3">
                            Last checked: {{ $row['last_checked_at'] ? $row['last_checked_at']->toDateTimeString() : 'Never' }}
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <a class="btn btn-sm btn-primary" href="{{ route('admin.integrations.accounts.index', ['provider' => $row['provider']]) }}">Connections</a>
                            @if($module)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.modules.edit', ['module' => $module->code ?: $module->module_key]) }}">Settings</a>
                                <form method="POST" action="{{ route('admin.modules.test-connection', ['module' => $module->code ?: $module->module_key]) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Test</button>
                                </form>
                                @if(!empty($module->documentation_url))
                                    <a class="btn btn-sm btn-outline-dark" href="{{ $module->documentation_url }}" target="_blank" rel="noopener">Docs</a>
                                @else
                                    <button class="btn btn-sm btn-outline-dark" type="button" disabled>Docs</button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-3">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.integrations.policies.index') }}">Open Access/Plan Matrix</a>
    </div>
@endsection
