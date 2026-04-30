@extends('layouts.admin')

@section('title', 'Integrations Access Matrix')

@section('admin-content')
    <x-ui.section-header
        title="Integrations Access Matrix"
        subtitle="Control provider access by tenant: enablement, operations, fallback behavior, and execution priority."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Connections</a>
        </x-slot:actions>
    </x-ui.section-header>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @foreach($tenants as $tenant)
        @php($rows = $matrixByTenant[$tenant->id] ?? [])
        <div class="card border-0 shadow-sm mb-3 integration-console-table">
            <div class="card-header bg-white">
                <strong>{{ $tenant->name }}</strong>
                <x-ui.badge tone="muted" class="ms-2">{{ strtoupper($tenant->plan_tier ?? 'basic') }}</x-ui.badge>
            </div>
            <div class="card-body">
                <p class="small as-muted mb-3">Multi-provider allows parallel chain selection; fallback continues to the next provider when primary search returns no usable result.</p>
                <div class="small text-muted mb-3">
                    <div><strong>Multi:</strong> Multi allows this provider to participate in a provider chain.</div>
                    <div><strong>Fallback:</strong> Fallback allows this provider to be used after another provider returns empty or fails according to policy.</div>
                </div>
                <form method="POST" action="{{ route('admin.integrations.access-matrix.update', $tenant) }}">
                    @csrf
                    @method('PUT')
                    <div class="table-responsive">
                        <table class="table table-sm align-middle as-table">
                            <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Mode</th>
                                <th>Enabled</th>
                                <th>Search</th>
                                <th>Pricing</th>
                                <th>Booking</th>
                                <th>Multi</th>
                                <th>Fallback</th>
                                <th>Priority</th>
                                <th>Source</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($rows as $idx => $row)
                                <tr>
                                    <td class="text-uppercase">
                                        {{ $row['provider'] }}
                                        @if(($row['provider'] ?? '') === 'sabre')
                                            <x-ui.badge tone="warning" class="ms-1">Search only</x-ui.badge>
                                        @endif
                                        <input type="hidden" name="rows[{{ $idx }}][provider]" value="{{ $row['provider'] }}">
                                        <input type="hidden" name="rows[{{ $idx }}][plan_code]" value="{{ $row['plan_code'] }}">
                                    </td>
                                    <td style="min-width: 150px;">
                                        <select class="form-select form-select-sm" name="rows[{{ $idx }}][environment]">
                                            @php($environment = strtolower((string) ($row['environment'] ?? 'production')))
                                            <option value="production" @selected($environment === 'production' || $environment === 'live')>Production</option>
                                            <option value="sandbox" @selected(in_array($environment, ['sandbox', 'test', 'testing', 'development'], true))>Sandbox / Test</option>
                                        </select>
                                    </td>
                                    @foreach(['is_enabled', 'can_search', 'can_price', 'can_book', 'allow_multi_provider', 'allow_fallback'] as $flag)
                                        <td>
                                            <input type="hidden" name="rows[{{ $idx }}][{{ $flag }}]" value="0">
                                            @php($isSabreSearchOnlyFlag = ($row['provider'] ?? '') === 'sabre' && in_array($flag, ['can_price', 'can_book'], true))
                                            <input class="form-check-input" type="checkbox" name="rows[{{ $idx }}][{{ $flag }}]" value="1" @checked((bool) ($row[$flag] ?? false)) @disabled($isSabreSearchOnlyFlag)>
                                        </td>
                                    @endforeach
                                    <td style="max-width: 120px;">
                                        <input class="form-control form-control-sm" type="number" min="1" max="9999" name="rows[{{ $idx }}][priority_order]" value="{{ (int) ($row['priority_order'] ?? 100) }}">
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="($row['source'] ?? '') === 'override' ? 'info' : 'muted'">
                                            {{ $row['source'] ?? 'plan_default' }}
                                        </x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary">Save {{ $tenant->name }} access</button>
                </form>
            </div>
        </div>
    @endforeach
@endsection
