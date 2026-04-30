@extends('layouts.admin')

@section('title', 'Bookings')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Bookings</h1>
    </div>

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="row g-2 mb-3 card card-body border-0 shadow-sm">
        <div class="col-md-3">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search number / customer">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(['pending','draft','on_hold','confirmed','cancelled'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ str_replace('_',' ', $st) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="agency_id" class="form-select form-select-sm">
                <option value="">All agencies</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? null) == $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm table-striped align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>Booking</th>
                <th>Customer</th>
                <th>Agency</th>
                <th>Status</th>
                <th>Total</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($bookings as $booking)
                <tr>
                    <td>{{ $booking->id }}</td>
                    <td>{{ $booking->booking_number }}</td>
                    <td>{{ $booking->customer_name ?? '—' }}</td>
                    <td>{{ $booking->agency?->name ?? '—' }}</td>
                    <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $booking->status) }}</span></td>
                    <td>{{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</td>
                    <td>
                        <div class="d-flex flex-wrap justify-content-end gap-1 action-cluster">
                            <a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No bookings found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $bookings->links() }}
@endsection
