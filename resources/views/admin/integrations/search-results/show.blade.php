@extends('layouts.admin')

@section('title', 'Flight Search Snapshot')

@section('admin-content')
    <x-ui.section-header
        title="Flight Search Snapshot #{{ $session->id }}"
        subtitle="Trace search request context, summary output, and normalized offer snapshots."
    >
        <x-slot:actions>
            <a href="{{ route('admin.integrations.search-results.index') }}" class="btn btn-outline-secondary">Back to Results</a>
        </x-slot:actions>
    </x-ui.section-header>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small as-muted">Provider</div><div class="fw-semibold text-uppercase">{{ $session->provider }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small as-muted">Status</div><div class="fw-semibold">{{ strtoupper($session->status ?? 'pending') }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small as-muted">Environment</div><div class="fw-semibold">{{ $session->environment ?? 'N/A' }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small as-muted">Offers</div><div class="fw-semibold">{{ $session->offerSnapshots->count() }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">Correlation ID</h2>
            <code>{{ $session->correlation_id }}</code>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">Request Snapshot</h2>
            <pre class="small mb-0">{{ json_encode($session->internal_request_snapshot ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">Search Summary</h2>
            <pre class="small mb-0">{{ json_encode($session->search_results_summary ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h6">Offer Snapshots</h2>
            @if($session->offerSnapshots->isEmpty())
                <x-ui.empty-state
                    title="No offer snapshots stored"
                    message="This search completed without stored offer rows. Check supplier response and mapping logs for this correlation ID."
                    icon="bi-inboxes"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle as-table">
                        <thead>
                        <tr>
                            <th>Offer Key</th>
                            <th>Provider Reference</th>
                            <th>Selected</th>
                            <th>Normalized Offer</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($session->offerSnapshots as $offer)
                            <tr>
                                <td><code>{{ $offer->offer_key }}</code></td>
                                <td>{{ $offer->provider_offer_reference ?? 'N/A' }}</td>
                                <td>{!! $offer->is_selected ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                                <td>
                                    <details>
                                        <summary class="small text-primary">View JSON</summary>
                                        <pre class="small mt-2">{{ json_encode($offer->normalized_offer ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
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
