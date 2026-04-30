@extends('layouts.admin')

@section('title', 'Integration Connection Detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Connection Detail</h1>
            <div class="text-muted small">
                <span class="text-uppercase">{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $connection->provider) ? 'AMADEUS SELF SERVICE' : strtoupper((string) $connection->provider) }}</span>
                <span class="mx-1">·</span>
                @if($connection->environment === 'production')
                    <span class="badge bg-danger">Production</span>
                @else
                    <span class="badge bg-info text-dark">Test</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 action-cluster">
            <a href="{{ route('admin.integrations.edit', $connection) }}" class="btn btn-outline-primary">Edit</a>
            <form method="POST" action="{{ route('admin.integrations.test', $connection) }}">
                @csrf
                <button class="btn btn-outline-secondary">Test connection</button>
            </form>
            <form method="POST" action="{{ route('admin.integrations.toggle', $connection) }}">
                @csrf
                <button class="btn {{ $connection->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    {{ $connection->is_active ? 'Disable' : 'Enable' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.integrations.default', $connection) }}">
                @csrf
                <button class="btn btn-outline-info">Set default</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Tenant/Company</dt>
                        <dd class="col-sm-8">{{ $connection->tenant?->name ?? 'Platform (global)' }}</dd>

                        <dt class="col-sm-4">Provider</dt>
                        <dd class="col-sm-8 text-uppercase">{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $connection->provider) ? 'AMADEUS SELF SERVICE' : strtoupper((string) $connection->provider) }}</dd>

                        <dt class="col-sm-4">Environment</dt>
                        <dd class="col-sm-8">
                            @if($connection->environment === 'production')
                                <span class="badge bg-danger">Production</span>
                                <span class="small text-muted ms-1">Live supplier traffic when app uses production credentials.</span>
                            @else
                                <span class="badge bg-info text-dark">Test</span>
                                <span class="small text-muted ms-1">Non-production / test supplier endpoints.</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Account name</dt>
                        <dd class="col-sm-8">{{ $connection->account_name ?? $connection->name }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            @php($st = $connection->status ?? 'untested')
                            <span class="badge {{ match($st) { 'healthy' => 'bg-success', 'failed' => 'bg-danger', 'disabled' => 'bg-secondary', default => 'bg-warning text-dark' } }}">{{ ucfirst($st) }}</span>
                        </dd>

                        <dt class="col-sm-4">Active</dt>
                        <dd class="col-sm-8">{{ $connection->is_active ? 'Yes' : 'No' }}</dd>

                        <dt class="col-sm-4">Default</dt>
                        <dd class="col-sm-8">{{ $connection->is_default ? 'Yes' : 'No' }}</dd>

                        <dt class="col-sm-4">Last tested</dt>
                        <dd class="col-sm-8">{{ $connection->last_tested_at?->toDateTimeString() ?? 'Never' }}</dd>

                        <dt class="col-sm-4">Last success</dt>
                        <dd class="col-sm-8">{{ $connection->last_success_at?->toDateTimeString() ?? 'Never' }}</dd>

                        <dt class="col-sm-4">Last failure</dt>
                        <dd class="col-sm-8">{{ $connection->last_failure_at?->toDateTimeString() ?? 'Never' }}</dd>

                        <dt class="col-sm-4">Failure reason</dt>
                        <dd class="col-sm-8">{{ $connection->last_failure_reason ?? 'None' }}</dd>

                        <dt class="col-sm-4">Supported operations</dt>
                        <dd class="col-sm-8">{{ implode(', ', $connection->supported_operations ?? []) ?: 'None' }}</dd>

                        <dt class="col-sm-4">Base currency</dt>
                        <dd class="col-sm-8">{{ strtoupper((string) ($connection->config['base_currency'] ?? 'PKR')) }}</dd>

                        <dt class="col-sm-4">Tax percent</dt>
                        <dd class="col-sm-8">{{ (float) ($connection->config['tax_percent'] ?? 0) }}%</dd>

                        <dt class="col-sm-4">Markup</dt>
                        <dd class="col-sm-8">
                            {{ ucfirst((string) ($connection->config['markup_type'] ?? 'percentage')) }}
                            ({{ (float) ($connection->config['markup_value'] ?? 0) }})
                        </dd>

                        <dt class="col-sm-4">Documentation</dt>
                        <dd class="col-sm-8">
                            @if(! empty($connection->config['documentation_url']))
                                <a href="{{ $connection->config['documentation_url'] }}" target="_blank" rel="noopener">Open provider doc</a>
                            @else
                                N/A
                            @endif
                        </dd>

                        <dt class="col-sm-4">Module notes</dt>
                        <dd class="col-sm-8">{{ $connection->config['module_notes'] ?? 'N/A' }}</dd>

                        <dt class="col-sm-4">Credential owner</dt>
                        <dd class="col-sm-8">{{ $connection->config['credential_owner'] ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-2">Stored credentials</h2>
                    <ul class="list-unstyled mb-0 small">
                        @forelse($connection->credentials as $credential)
                            <li class="mb-2">
                                <strong>{{ $credential->credential_key }}</strong>
                                <div class="text-muted">Stored securely (hidden)</div>
                            </li>
                        @empty
                            <li class="text-muted">No credentials configured.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
