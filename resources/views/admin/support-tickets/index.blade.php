@extends('layouts.admin')

@section('title', 'Support Desk')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Support Desk</h1>
        <span class="text-muted small">Ticket system with SLA + escalation</span>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.support-tickets.index') }}" class="row g-2 mb-3">
                <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search ticket #" value="{{ $filters['q'] ?? '' }}"></div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All priority</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ ucfirst($priority->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assigned_to_user_id" class="form-select form-select-sm">
                        <option value="">All assignees</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((int)($filters['assigned_to_user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Filter</button></div>
            </form>

            <form method="POST" action="{{ route('admin.support-tickets.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4"><input type="text" name="subject" class="form-control form-control-sm" placeholder="Subject" required></div>
                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm" required>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ ucfirst($priority->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assigned_to_user_id" class="form-select form-select-sm">
                        <option value="">Assign later</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="agency_id" class="form-select form-select-sm">
                        <option value="">No agency</option>
                        @foreach($agencies as $agency)
                            <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">No customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ trim($customer->first_name.' '.$customer->last_name) ?: $customer->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12"><textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Describe issue..." required></textarea></div>
                <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary">Create ticket</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th><th>Subject</th><th>Status</th><th>Priority</th><th>Assignee</th><th>SLA (resolution)</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->ticket_number }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $ticket->status->value)) }}</td>
                            <td>{{ ucfirst($ticket->priority->value) }}</td>
                            <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                            <td>{{ optional($ticket->resolution_due_at)->format('d M Y H:i') ?? '-' }}</td>
                            <td><a href="{{ route('admin.support-tickets.show', $ticket) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No tickets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $tickets->links() }}</div>
    </div>
@endsection
