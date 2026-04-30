@extends('layouts.admin')

@section('title', 'Promo Codes')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Promo Codes</h1>
        <a href="{{ route('admin.promo-codes.create') }}" class="btn btn-sm btn-primary">New Promo Code</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Usage</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @forelse($codes as $promo)
                    <tr>
                        <td><code>{{ $promo->code }}</code></td>
                        <td>{{ $promo->discount_type }}</td>
                        <td>{{ number_format((float) $promo->discount_value, 2) }}</td>
                        <td>{{ $promo->usage_count }} / {{ $promo->usage_limit ?? 'unlimited' }}</td>
                        <td>{{ $promo->is_active ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.promo-codes.edit', $promo) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No promo codes yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $codes->links() }}</div>
    </div>
@endsection
