@extends('layouts.admin')

@section('title', 'Integrations Providers Connections')

@section('admin-content')
    <x-ui.section-header
        title="API Credentials"
        subtitle="Manage supplier account credentials per environment with connection health visibility."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.providers.index') }}" class="btn btn-outline-secondary">Providers</a>
            <a href="{{ route('admin.integrations.policies.index') }}" class="btn btn-outline-secondary">Access Matrix</a>
            <a href="{{ route('admin.integrations.accounts.create') }}" class="btn btn-primary">Add supplier account</a>
        </x-slot:actions>
    </x-ui.section-header>
    @if(!empty($providerFilter))
        <div class="alert alert-info py-2">Filtered by provider: <strong>{{ strtoupper($providerFilter) }}</strong></div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm integration-console-table">
        <div class="card-body">
            @if($accounts->isEmpty())
                <x-ui.empty-state
                    title="No supplier accounts configured"
                    message="Add a supplier account to enable sandbox/production credential testing and runtime routing."
                    icon="bi-key"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle as-table">
                        <thead>
                        <tr>
                            <th>Account</th>
                            <th>Provider</th>
                            <th>Credential ownership</th>
                            <th>Environments</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($accounts as $accountKey => $connections)
                            @php
                                /** @var \App\Models\IntegrationConnection $first */
                                $first = $connections->first();
                                $test = $connections->firstWhere('environment', 'test');
                                $production = $connections->firstWhere('environment', 'production');
                                $default = $connections->firstWhere('is_default', true);
                            @endphp
                            <tr>
                                <td>{{ $first->name }}</td>
                                <td class="text-uppercase">{{ $first->provider }}</td>
                                <td>
                                    <div class="small">{{ strtoupper(str_replace('_', ' ', $first->ownership_type ?? 'tenant')) }}</div>
                                    <div class="text-muted small">{{ $first->ownershipTenant?->name ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <x-ui.badge :tone="$test?->is_active ? 'success' : 'muted'">Sandbox {{ $test?->is_active ? 'active' : 'inactive' }}</x-ui.badge>
                                    <x-ui.badge :tone="$production?->is_active ? 'success' : 'muted'">Production {{ $production?->is_active ? 'active' : 'inactive' }}</x-ui.badge>
                                </td>
                                <td>
                                    @if($default)
                                        <x-ui.badge tone="info">{{ strtoupper($default->environment) }}</x-ui.badge>
                                    @else
                                        <span class="text-muted small">Not set</span>
                                    @endif
                                </td>
                                <td>
                                    @foreach([$test, $production] as $conn)
                                        @if($conn)
                                            <div class="small mb-1">
                                                <strong>{{ strtoupper($conn->environment) }}:</strong>
                                                <span class="badge {{ $conn->last_tested_status === 'ok' ? 'bg-success' : ($conn->last_tested_status === 'failed' ? 'bg-danger' : 'bg-secondary') }}">
                                                    {{ $conn->last_tested_status ? strtoupper($conn->last_tested_status) : 'N/A' }}
                                                </span>
                                                @if($conn->last_checked_at)
                                                    <span class="text-muted">checked {{ $conn->last_checked_at->diffForHumans() }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-1 action-cluster">
                                    @if($test)
                                        <a href="{{ route('admin.integrations.accounts.show', $test->id) }}" class="btn btn-sm btn-outline-dark">Detail (Sandbox)</a>
                                    @elseif($production)
                                        <a href="{{ route('admin.integrations.accounts.show', $production->id) }}" class="btn btn-sm btn-outline-dark">Detail (Production)</a>
                                    @endif
                                    <a href="{{ route('admin.integrations.accounts.edit', $first->account_key ?? $accountKey) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @if($test)
                                        <form method="post" action="{{ route('admin.integrations.accounts.test-connection') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="connection_id" value="{{ $test->id }}">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Test Sandbox</button>
                                        </form>
                                    @endif
                                    @if($production)
                                        <form method="post" action="{{ route('admin.integrations.accounts.test-connection') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="connection_id" value="{{ $production->id }}">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Test Production</button>
                                        </form>
                                    @endif
                                    @if($test)
                                        <form method="post" action="{{ route('admin.integrations.accounts.default') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="connection_id" value="{{ $test->id }}">
                                            <button class="btn btn-sm btn-outline-info" type="submit">Default Sandbox</button>
                                        </form>
                                    @endif
                                    @if($production)
                                        <form method="post" action="{{ route('admin.integrations.accounts.default') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="connection_id" value="{{ $production->id }}">
                                            <button class="btn btn-sm btn-outline-info" type="submit">Default Production</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.integrations.accounts.destroy', $first->account_key ?? $accountKey) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete supplier account?')">Delete</button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
