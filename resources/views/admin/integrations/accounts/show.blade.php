@extends('layouts.admin')

@section('title', 'Connection detail')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Connection detail</h1>
        <a href="{{ route('admin.integrations.accounts.edit', $connection->account_key) }}" class="btn btn-primary btn-sm">Edit Account</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Label</dt>
                <dd class="col-sm-9">{{ $connection->name }}</dd>

                <dt class="col-sm-3">Provider</dt>
                <dd class="col-sm-9 text-uppercase">{{ $connection->provider }}</dd>

                <dt class="col-sm-3">Environment</dt>
                <dd class="col-sm-9">{{ strtoupper($connection->environment) }}</dd>

                <dt class="col-sm-3">Credential ownership</dt>
                <dd class="col-sm-9">{{ strtoupper(str_replace('_', ' ', $connection->ownership_type ?? 'tenant')) }}</dd>

                <dt class="col-sm-3">Owner tenant/company</dt>
                <dd class="col-sm-9">{{ $connection->ownershipTenant?->name ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Access tenant (legacy field)</dt>
                <dd class="col-sm-9">{{ $connection->tenant?->name ?? 'Global' }}</dd>

                <dt class="col-sm-3">Base URL</dt>
                <dd class="col-sm-9">{{ $connection->base_url ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    <span class="badge {{ $connection->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $connection->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="badge {{ $connection->last_tested_status === 'ok' ? 'bg-success' : ($connection->last_tested_status === 'failed' ? 'bg-danger' : 'bg-secondary') }}">
                        {{ $connection->last_tested_status ? strtoupper($connection->last_tested_status) : 'N/A' }}
                    </span>
                </dd>

                <dt class="col-sm-3">Last checked</dt>
                <dd class="col-sm-9">{{ $connection->last_checked_at?->toDateTimeString() ?? 'Never' }}</dd>

                <dt class="col-sm-3">Last success</dt>
                <dd class="col-sm-9">{{ $connection->last_success_at?->toDateTimeString() ?? 'Never' }}</dd>

                <dt class="col-sm-3">Last failure reason</dt>
                <dd class="col-sm-9">{{ $connection->last_failure_reason ?? 'None' }}</dd>

                <dt class="col-sm-3">Stored credential keys</dt>
                <dd class="col-sm-9">{{ $connection->credentials->pluck('key_name')->implode(', ') ?: 'None' }}</dd>
            </dl>
        </div>
    </div>
@endsection
