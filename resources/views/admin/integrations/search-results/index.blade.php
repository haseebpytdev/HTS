@extends('layouts.admin')

@section('title', 'Flight Search Results')

@section('admin-content')
    <x-ui.section-header
        title="Flight Search Results"
        subtitle="Review normalized supplier search snapshots, correlation IDs, and offer counts."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Back to Integration Hub</a>
        </x-slot:actions>
    </x-ui.section-header>

    <form method="GET" action="{{ route('admin.integrations.search-results.index') }}" class="card card-body border-0 shadow-sm mb-3 integration-console-filter">
        <div class="row g-2">
            <div class="col-md-2">
                <select name="provider" class="form-select">
                    <option value="">Provider</option>
                    @php($selectedProvider = \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::normalize((string) ($filters['provider'] ?? '')))
                    @foreach([
                        'travelport' => 'TRAVELPORT',
                        'sabre' => 'SABRE',
                        \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE => 'AMADEUS SELF SERVICE',
                        'iati' => 'IATI',
                        'duffel' => 'DUFFEL',
                    ] as $provider => $label)
                        <option value="{{ $provider }}" @selected($selectedProvider === $provider)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Status</option>
                    @foreach(['pending', 'completed', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="correlation_id" class="form-control" value="{{ $filters['correlation_id'] ?? '' }}" placeholder="Correlation ID">
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-primary" type="submit">Go</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm integration-console-table">
        <div class="card-body">
            @if($sessions->isEmpty())
                <x-ui.empty-state
                    title="No flight search snapshots yet"
                    message="Run a supplier-backed flight search first. Completed sessions will appear here for auditing and diagnostics."
                    icon="bi-search"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle as-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>Correlation ID</th>
                            <th>Offers</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($sessions as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td class="text-uppercase">{{ \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::matches((string) $row->provider) ? 'AMADEUS SELF SERVICE' : strtoupper((string) $row->provider) }}</td>
                                <td><span class="badge {{ $row->status === 'completed' ? 'bg-success' : ($row->status === 'failed' ? 'bg-danger' : 'bg-secondary') }}">{{ strtoupper($row->status ?? 'pending') }}</span></td>
                                <td><code>{{ $row->correlation_id }}</code></td>
                                <td>{{ (int) $row->offer_snapshots_count }}</td>
                                <td>{{ $row->created_at?->diffForHumans() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.integrations.search-results.show', $row) }}" class="btn btn-sm btn-outline-dark">View</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $sessions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
