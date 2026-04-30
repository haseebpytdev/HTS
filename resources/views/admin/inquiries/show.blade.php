@extends('layouts.admin')

@section('title', 'Manage Inquiry')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Inquiry #{{ $inquiry->id }}</h1>
        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Lead Info</h2>
                    <p class="mb-1"><strong>Name:</strong> {{ $inquiry->name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $inquiry->email ?: '-' }}</p>
                    <p class="mb-1"><strong>Phone:</strong> {{ $inquiry->phone ?: '-' }}</p>
                    <p class="mb-1"><strong>Source:</strong> {{ ucfirst($inquiry->source) }}</p>
                    <p class="mb-1"><strong>Travel Date:</strong> {{ $inquiry->travel_date?->format('d M Y') ?: '-' }}</p>
                    <p class="mb-1"><strong>Pax:</strong> {{ $inquiry->adults }} Adult / {{ $inquiry->children }} Child</p>
                    <p class="mb-1"><strong>Assigned:</strong> {{ $inquiry->assignedTo?->name ?? 'Unassigned' }}</p>
                    <p class="mb-0"><strong>Message:</strong> {{ $inquiry->message ?: '-' }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Linked Data</h2>
                    <p class="mb-1"><strong>Package:</strong> {{ $inquiry->package->title ?? '-' }}</p>
                    <p class="mb-1"><strong>Group:</strong> {{ $inquiry->group->name ?? '-' }}</p>
                    <p class="mb-1"><strong>Agency:</strong> {{ $inquiry->agency->name ?? '-' }}</p>
                    <p class="mb-0"><strong>Quotations:</strong> {{ $inquiry->quotations->pluck('quote_number')->join(', ') ?: '-' }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Activity timeline</h2>
                    @forelse($inquiry->activities as $act)
                        <div class="border-start border-3 border-secondary ps-2 mb-3">
                            <div class="small text-muted">
                                {{ $act->occurred_at->format('d M Y H:i') }}
                                @if($act->user)
                                    · {{ $act->user->name }}
                                @endif
                            </div>
                            <div class="fw-semibold">{{ str_replace('_', ' ', $act->type->value) }}</div>
                            @if($act->title)
                                <div>{{ $act->title }}</div>
                            @endif
                            @if($act->body)
                                <div class="small text-body-secondary mt-1">{{ $act->body }}</div>
                            @endif
                            @if($act->metadata && ($act->type->value === 'status_change' || $act->type->value === 'pipeline_change'))
                                <div class="small mt-1">
                                    @if(!empty($act->metadata['from']) || !empty($act->metadata['to']))
                                        <span class="text-muted">{{ $act->metadata['from'] ?? '—' }}</span>
                                        →
                                        <span class="text-muted">{{ $act->metadata['to'] ?? '—' }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No activities yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Follow-ups</h2>
                    @forelse($inquiry->followUps as $fu)
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $fu->title }}</div>
                                <div class="small text-muted">Due {{ $fu->due_at->format('d M Y H:i') }}</div>
                                @if($fu->description)
                                    <div class="small mt-1">{{ $fu->description }}</div>
                                @endif
                                @if($fu->assignee)
                                    <div class="small text-muted">Assigned: {{ $fu->assignee->name }}</div>
                                @endif
                                @if($fu->completed_at)
                                    <span class="badge text-bg-success">Done {{ $fu->completed_at->format('d M Y') }}</span>
                                @endif
                            </div>
                            @if($fu->isOpen())
                                <form method="POST" action="{{ route('admin.inquiries.crm.follow-ups.complete', [$inquiry, $fu]) }}" class="ms-2 text-end" style="min-width: 11rem;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="note" class="form-control form-control-sm mb-1" placeholder="Note (optional)" maxlength="5000">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Complete</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No follow-ups scheduled.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Sales pipeline</h2>
                    <form method="POST" action="{{ route('admin.inquiries.crm.pipeline', $inquiry) }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-2">
                            <label class="form-label">Pipeline stage</label>
                            <select name="pipeline_stage" class="form-select">
                                @foreach($pipelineStages as $stage)
                                    <option value="{{ $stage->value }}" @selected($inquiry->pipeline_stage->value === $stage->value)>{{ $stage->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Estimated value (optional)</label>
                            <input type="number" step="0.01" min="0" name="estimated_value" class="form-control" value="{{ old('estimated_value', $inquiry->estimated_value) }}" placeholder="e.g. 250000">
                        </div>
                        <button class="btn btn-outline-primary btn-sm">Update pipeline</button>
                    </form>
                    @if($inquiry->last_contacted_at)
                        <p class="small text-muted mt-2 mb-0">Last contact: {{ $inquiry->last_contacted_at->format('d M Y H:i') }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Log call</h2>
                    <form method="POST" action="{{ route('admin.inquiries.crm.calls', $inquiry) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Title (optional)</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="120">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Summary</label>
                            <textarea name="summary" rows="3" class="form-control" required>{{ old('summary') }}</textarea>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm">Save call</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Add note</h2>
                    <form method="POST" action="{{ route('admin.inquiries.crm.notes', $inquiry) }}">
                        @csrf
                        <div class="mb-2">
                            <textarea name="body" rows="3" class="form-control" required>{{ old('body') }}</textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-light border inquiry-note-template" data-note-template="Called customer; awaiting response.">Awaiting response</button>
                            <button type="button" class="btn btn-sm btn-outline-light border inquiry-note-template" data-note-template="Sent quotation details and follow-up timeline.">Quote sent</button>
                            <button type="button" class="btn btn-sm btn-outline-light border inquiry-note-template" data-note-template="Customer requested revision on travel dates or pricing.">Revision requested</button>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm">Add note</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Schedule follow-up</h2>
                    <form method="POST" action="{{ route('admin.inquiries.crm.follow-ups.store', $inquiry) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="200">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Due</label>
                            <input type="datetime-local" name="due_at" class="form-control" value="{{ old('due_at') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Assign (optional)</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">—</option>
                                @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm">Schedule</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Status Workflow</h2>
                    <form method="POST" action="{{ route('admin.inquiries.update-status', $inquiry) }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected($inquiry->status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" rows="4" class="form-control">{{ old('admin_notes', $inquiry->admin_notes) }}</textarea>
                        </div>
                        <button class="btn btn-primary">Update Inquiry</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm" id="convert-intent">
                <div class="card-body">
                    <h2 class="h6 mb-3">Convert to Booking Intent</h2>
                    <form method="POST" action="{{ route('admin.inquiries.convert-booking-intent', $inquiry) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Select Quotation</label>
                            <select name="quotation_id" class="form-select" required>
                                <option value="">Select quote</option>
                                @foreach($eligibleQuotations as $quote)
                                    <option value="{{ $quote->id }}">{{ $quote->quote_number }} ({{ ucfirst($quote->status) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Conversion Note</label>
                            <textarea name="note" rows="3" class="form-control" placeholder="Optional note"></textarea>
                        </div>
                        <button class="btn btn-success" @disabled($eligibleQuotations->isEmpty())>Create Booking Intent</button>
                        @if($eligibleQuotations->isEmpty())
                            <p class="small text-muted mt-2 mb-0">No quotation linked to this inquiry yet.</p>
                        @else
                            <p class="small text-muted mt-2 mb-0">Helper: select latest approved quote and keep a short conversion note for audit clarity.</p>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.inquiry-note-template').forEach(function (button) {
            button.addEventListener('click', function () {
                const textarea = document.querySelector('form[action="{{ route('admin.inquiries.crm.notes', $inquiry) }}"] textarea[name="body"]');
                if (!textarea) return;
                textarea.value = button.getAttribute('data-note-template') || '';
                textarea.focus();
            });
        });
    </script>
@endsection
