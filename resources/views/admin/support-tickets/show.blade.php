@extends('layouts.admin')

@section('title', 'Support Ticket')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $ticket->ticket_number }} - {{ $ticket->subject }}</h1>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <p class="mb-1"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $ticket->status->value)) }}</p>
                    <p class="mb-1"><strong>Priority:</strong> {{ ucfirst($ticket->priority->value) }}</p>
                    <p class="mb-1"><strong>First response due:</strong> {{ optional($ticket->first_response_due_at)->format('d M Y H:i') ?? '-' }}</p>
                    <p class="mb-3"><strong>Resolution due:</strong> {{ optional($ticket->resolution_due_at)->format('d M Y H:i') ?? '-' }}</p>
                    <p class="mb-0">{{ $ticket->description }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Replies</strong></div>
                <div class="card-body">
                    @forelse($ticket->replies as $reply)
                        <div class="border rounded p-2 mb-2">
                            <div class="small text-muted mb-1">
                                {{ $reply->user?->name ?? 'System' }} · {{ $reply->created_at->format('d M Y H:i') }} · {{ $reply->is_internal ? 'Internal' : 'Public' }}
                            </div>
                            <div>{{ $reply->message }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No replies yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Add Reply</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket) }}">
                        @csrf
                        <textarea name="message" rows="4" class="form-control form-control-sm mb-2" required></textarea>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="is_internal">
                            <label class="form-check-label small" for="is_internal">Internal note only</label>
                        </div>
                        <button class="btn btn-sm btn-primary">Post reply</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Resolve</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.support-tickets.resolve', $ticket) }}">
                        @csrf
                        <textarea name="note" rows="3" class="form-control form-control-sm mb-2" placeholder="Optional resolution note"></textarea>
                        <button class="btn btn-sm btn-success">Mark resolved</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Escalate</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.support-tickets.escalate', $ticket) }}">
                        @csrf
                        <select name="to_user_id" class="form-select form-select-sm mb-2" required>
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <textarea name="reason" rows="3" class="form-control form-control-sm mb-2" placeholder="Escalation reason" required></textarea>
                        <button class="btn btn-sm btn-warning">Escalate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
