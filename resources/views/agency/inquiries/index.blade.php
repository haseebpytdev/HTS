@extends('layouts.agency')

@section('title', 'Inquiry History')

@section('agency-content')
    <h1 class="h4 mb-3">Inquiry History</h1>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Travel Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inquiries as $inquiry)
                    <tr>
                        <td>{{ $inquiry->id }}</td>
                        <td>{{ $inquiry->name }}</td>
                        <td>{{ $inquiry->email }}</td>
                        <td>{{ optional($inquiry->travel_date)->format('d M Y') }}</td>
                        <td>{{ ucfirst($inquiry->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4">No inquiries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $inquiries->links() }}</div>
@endsection
