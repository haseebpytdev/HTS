@extends('layouts.admin')

@section('title', 'Booking '.$booking->booking_number)

@section('admin-content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Booking {{ $booking->booking_number }}</h1>
            <small class="text-muted">{{ str_replace('_', ' ', $booking->status) }} · {{ $booking->agency?->name ?? '—' }}</small>
        </div>
        <div class="d-flex flex-wrap gap-2 action-cluster">
            <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary">All bookings</a>
            <a href="{{ route('admin.bookings.voucher', $booking) }}" class="btn btn-sm btn-dark" target="_blank">Voucher</a>
            <a href="{{ route('admin.bookings.invoice', $booking) }}" class="btn btn-sm btn-outline-dark" target="_blank">Invoice</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6">Customer</h2>
                    <p class="mb-1"><strong>Name:</strong> {{ $booking->customer_name ?? '—' }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $booking->customer_email ?? '—' }}</p>
                    <p class="mb-1"><strong>Phone:</strong> {{ $booking->customer_phone ?? '—' }}</p>
                    <p class="mb-0"><strong>Travel:</strong> {{ $booking->travel_date?->format('d M Y') ?? '—' }}
                        @if($booking->return_date)
                            → {{ $booking->return_date->format('d M Y') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Line items</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Type</th><th>Title</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                            @foreach($booking->items as $item)
                                <tr>
                                    <td>{{ $item->item_type }}</td>
                                    <td>{{ $item->title }}</td>
                                    <td class="text-end">{{ number_format((float) $item->total_price, 2) }} {{ $booking->currency }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($booking->promo_code)
                        <p class="mb-1 text-end small text-muted">
                            Promo: {{ $booking->promo_code }} (-{{ number_format((float) ($booking->promo_discount_amount ?? 0), 2) }} {{ $booking->currency }})
                        </p>
                    @endif
                    <p class="mb-0 text-end"><strong>Total:</strong> {{ number_format((float) $booking->total_amount, 2) }} {{ $booking->currency }}</p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Travelers</h2>
                    <ul class="list-unstyled mb-0 small">
                        @forelse($booking->travelers as $t)
                            <li>{{ $t->first_name }} {{ $t->last_name }} <span class="text-muted">({{ $t->traveler_type }})</span></li>
                        @empty
                            <li class="text-muted">No travelers.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Documents</h2>
                    <form method="POST" action="{{ route('admin.bookings.documents.store', $booking) }}" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="row g-2">
                            <div class="col-sm-4">
                                <label class="form-label small">Type</label>
                                <select name="document_type" class="form-select form-select-sm" required>
                                    @foreach(['passport', 'visa', 'ticket', 'payment_proof', 'voucher', 'invoice', 'other'] as $docType)
                                        <option value="{{ $docType }}">{{ str_replace('_', ' ', ucfirst($docType)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small">File</label>
                                <input type="file" name="file" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-sm-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Upload</button>
                            </div>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_customer_visible" id="doc-visible" value="1" checked>
                            <label class="form-check-label small" for="doc-visible">Visible to customer</label>
                        </div>
                        <input type="text" name="notes" class="form-control form-control-sm mt-2" placeholder="Notes (optional)">
                        @error('document_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Version</th>
                                    <th>File</th>
                                    <th>Scan</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                    <th>Download</th>
                                    <th class="text-end">Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($booking->documents as $doc)
                                    @php
                                        $latestScanTask = $documentScanTasks->firstWhere('reference_id', $doc->id);
                                        $scanEngine = data_get($latestScanTask?->result, 'engine', '—');
                                        $scanMessage = data_get($latestScanTask?->result, 'message');
                                        $scanCheckedAt = data_get($latestScanTask?->result, 'checked_at');
                                        $scanSignature = data_get($latestScanTask?->result, 'signature');
                                        $isRescanEligible = in_array($doc->virus_scan_status, ['failed', 'suspicious'], true)
                                            || $doc->virus_scan_status === null
                                            || $doc->virus_scanned_at === null;
                                    @endphp
                                    <tr>
                                        <td>{{ str_replace('_', ' ', ucfirst($doc->document_type)) }}</td>
                                        <td class="small">v{{ $doc->version_number }}</td>
                                        <td class="small">{{ $doc->original_name }}</td>
                                        <td>
                                            @php
                                                $scanBadgeClass = match ($doc->virus_scan_status) {
                                                    'clean' => 'text-bg-success',
                                                    'infected' => 'text-bg-danger',
                                                    'suspicious' => 'text-bg-warning',
                                                    'failed' => 'text-bg-danger',
                                                    'pending_scan', null => 'text-bg-secondary',
                                                    default => 'text-bg-light border',
                                                };
                                            @endphp
                                            <span class="badge {{ $scanBadgeClass }}">{{ $doc->virus_scan_status ?? 'pending_scan' }}</span>
                                            @if($doc->isQuarantined())
                                                <span class="badge text-bg-danger-subtle border border-danger text-danger mt-1">Quarantined</span>
                                            @elseif($doc->deleted_at !== null)
                                                <span class="badge text-bg-secondary mt-1">Archived</span>
                                            @endif
                                            <div class="small text-muted mt-1">Engine: {{ $scanEngine }}</div>
                                            <div class="small text-muted">Last scanned:
                                                {{ $doc->virus_scanned_at?->format('d M Y H:i') ?? ($scanCheckedAt ? \Carbon\Carbon::parse($scanCheckedAt)->format('d M Y H:i') : '—') }}
                                            </div>
                                            @if($scanSignature)
                                                <div class="small text-muted">Signature: {{ $scanSignature }}</div>
                                            @endif
                                            @if($doc->virus_scan_note)
                                                <div class="small text-muted mt-1">{{ $doc->virus_scan_note }}</div>
                                            @elseif($scanMessage)
                                                <div class="small text-muted mt-1">{{ $scanMessage }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge text-bg-light border">{{ $doc->validation_status }}</span>
                                            @if($doc->validation_note)
                                                <div class="small text-muted mt-1">{{ $doc->validation_note }}</div>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $doc->created_at?->format('d M Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.bookings.documents.download', [$booking, $doc]) }}" class="btn btn-sm btn-outline-dark">Download</a>
                                            @if($isRescanEligible)
                                                <form method="POST" action="{{ route('admin.bookings.documents.rescan', [$booking, $doc]) }}" class="mt-1">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Rescan</button>
                                                </form>
                                            @endif
                                            @if($doc->deleted_at === null)
                                                <form method="POST" action="{{ route('admin.bookings.documents.archive', [$booking, $doc]) }}" class="mt-1">
                                                    @csrf
                                                    <input type="hidden" name="archive_note" value="Archived from booking screen">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Archive</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td class="text-end" style="min-width: 220px;">
                                            <form method="POST" action="{{ route('admin.bookings.documents.validate', [$booking, $doc]) }}" class="d-flex flex-column gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <div class="d-flex gap-1">
                                                    <select name="validation_status" class="form-select form-select-sm">
                                                        @foreach(['pending', 'approved', 'rejected'] as $status)
                                                            <option value="{{ $status }}" @selected($doc->validation_status === $status)>{{ ucfirst($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Save</button>
                                                </div>
                                                <div class="form-check d-flex justify-content-end align-items-center gap-1">
                                                    <input class="form-check-input" type="checkbox" name="is_customer_visible" value="1" id="visible-{{ $doc->id }}" @checked($doc->is_customer_visible)>
                                                    <label class="form-check-label small" for="visible-{{ $doc->id }}">Customer visible</label>
                                                </div>
                                                <input type="text" name="validation_note" class="form-control form-control-sm" placeholder="Validation note" value="{{ $doc->validation_note }}">
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-2">No documents uploaded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <h3 class="h6 mb-2">Background scan tasks</h3>
                        <ul class="small mb-0">
                            @forelse($documentScanTasks as $task)
                                <li>#{{ $task->id }} · doc {{ $task->reference_id }} · {{ $task->status }} @if($task->error_message) · {{ $task->error_message }} @endif</li>
                            @empty
                                <li class="text-muted">No background scan tasks yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Supplier costs &amp; P&amp;L</h2>
                    <form method="POST" action="{{ route('admin.bookings.supplier-cost.update', $booking) }}" class="row g-2 mb-2">
                        @csrf
                        <div class="col-sm-6">
                            <label class="form-label small">Supplier total cost</label>
                            <input type="number" step="0.01" min="0" name="supplier_cost_total" class="form-control form-control-sm" value="{{ old('supplier_cost_total', $booking->supplier_cost_total) }}" required>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small">Currency</label>
                            <input type="text" name="supplier_cost_currency" class="form-control form-control-sm" value="{{ old('supplier_cost_currency', $booking->supplier_cost_currency ?: $booking->currency) }}">
                        </div>
                        <div class="col-sm-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Save cost</button>
                        </div>
                    </form>
                    @php
                        $supplierCost = (float) ($booking->supplier_cost_total ?? 0);
                        $bookingRevenue = (float) ($booking->total_amount ?? 0);
                        $netMargin = $bookingRevenue - $supplierCost;
                        $netMarginPct = $bookingRevenue > 0 ? ($netMargin / $bookingRevenue) * 100 : 0;
                    @endphp
                    <p class="small mb-1">Booking value: <strong>{{ number_format($bookingRevenue, 2) }} {{ $booking->currency }}</strong></p>
                    <p class="small mb-1">Supplier costs: <strong>{{ number_format($supplierCost, 2) }} {{ $booking->supplier_cost_currency ?: $booking->currency }}</strong></p>
                    <p class="small mb-1">Net margin: <strong>{{ number_format($netMargin, 2) }} {{ $booking->currency }}</strong> ({{ number_format($netMarginPct, 2) }}%)</p>
                    @if($booking->supplier_cost_recorded_at)
                        <p class="small text-muted mb-0">Captured {{ $booking->supplier_cost_recorded_at->format('d M Y H:i') }} by {{ $booking->supplierCostRecordedBy?->name ?? 'system' }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Payments</h2>
                    <p class="small mb-2">
                        Paid: <strong>{{ $paymentPanel['paid_total'] }}</strong> {{ $booking->currency }}
                        · Balance due: <strong>{{ $paymentPanel['balance_due'] }}</strong> {{ $booking->currency }}
                    </p>
                    @if($paymentPanel['wallet'])
                        <p class="small text-muted mb-3">
                            Agency wallet: {{ number_format((float) $paymentPanel['wallet']->balance, 2) }} {{ $paymentPanel['wallet']->currency }}
                            @if($paymentPanel['wallet']->is_overdue)
                                <span class="badge text-bg-warning">Overdue</span>
                            @endif
                        </p>
                    @endif

                    @if(bccomp($paymentPanel['balance_due'], '0', 2) === 1)
                        <form method="POST" action="{{ route('admin.bookings.payments.deposit', $booking) }}" class="mb-2">
                            @csrf
                            <label class="form-label small">Deposit amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm mb-1" value="{{ old('amount') }}" required>
                            <input type="text" name="idempotency_key" class="form-control form-control-sm mb-2" placeholder="Idempotency key (optional)" value="{{ old('idempotency_key') }}">
                            @error('amount')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Record deposit (gateway)</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bookings.payments.balance', $booking) }}" class="mb-2">
                            @csrf
                            <label class="form-label small">Balance installment</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm mb-1" value="{{ old('amount') }}" required>
                            <input type="text" name="idempotency_key" class="form-control form-control-sm mb-2" placeholder="Idempotency key (optional)" value="{{ old('idempotency_key') }}">
                            @error('amount')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Record balance payment (gateway)</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bookings.payments.full', $booking) }}" class="mb-2" onsubmit="return confirm('Capture full remaining balance via gateway?');">
                            @csrf
                            <input type="text" name="idempotency_key" class="form-control form-control-sm mb-2" placeholder="Idempotency key (optional)" value="{{ old('idempotency_key') }}">
                            @error('idempotency_key')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-sm btn-primary w-100">Pay full balance due (gateway)</button>
                        </form>
                        @if($booking->agency_id)
                            <form method="POST" action="{{ route('admin.bookings.payments.wallet', $booking) }}" class="mb-3">
                                @csrf
                                <label class="form-label small">Pay from agency wallet</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm mb-1" value="{{ old('amount') }}" required>
                                <input type="text" name="idempotency_key" class="form-control form-control-sm mb-2" placeholder="Idempotency key (optional)" value="{{ old('idempotency_key') }}">
                                @error('amount')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-sm btn-outline-success w-100">Apply wallet to booking</button>
                            </form>
                        @endif
                    @else
                        <p class="small text-muted mb-3">No balance due.</p>
                    @endif

                    @if($paymentPanel['recent_payments']->isNotEmpty())
                        <p class="small text-muted mb-1">Recent payments</p>
                        <ul class="list-unstyled small mb-2">
                            @foreach($paymentPanel['recent_payments'] as $p)
                                <li class="mb-1 border-bottom pb-1">
                                    #{{ $p->id }} · {{ str_replace('_', ' ', $p->flow_type) }} · {{ $p->status }} ·
                                    {{ number_format((float) $p->amount, 2) }} {{ $p->currency }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach($paymentPanel['refundable_payments'] as $p)
                        <form method="POST" action="{{ route('admin.payments.refund', $p) }}" class="mb-2 border rounded p-2 bg-light">
                            @csrf
                            <span class="small fw-semibold">Refund payment #{{ $p->id }}</span>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm my-1" placeholder="Amount" required>
                            <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="Reason (optional)">
                            @error('amount')<div class="text-danger small">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-sm btn-outline-danger">Submit refund</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Customer portal (B2C)</h2>
                    @if($booking->customer)
                        <p class="small mb-2">Linked: <strong>{{ $booking->customer->email }}</strong> ({{ $booking->customer->fullName() }})</p>
                    @else
                        <p class="small text-muted mb-2">No customer account linked. They will not see this booking in /customer until linked.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.bookings.assign-customer', $booking) }}" class="d-flex flex-column gap-2">
                        @csrf
                        <label class="form-label small mb-0">Link by customer email</label>
                        <input type="email" name="customer_email" class="form-control form-control-sm" value="{{ old('customer_email', $booking->customer?->email) }}" placeholder="registered@customer.com" required>
                        @error('customer_email')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save link</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Lifecycle</h2>
                    @if(in_array($booking->status, ['draft','pending'], true))
                        <form method="POST" action="{{ route('admin.bookings.hold', $booking) }}" class="mb-2">
                            @csrf
                            <label class="form-label small">Hold until (optional)</label>
                            <input type="datetime-local" name="hold_expires_at" class="form-control form-control-sm mb-2">
                            <button type="submit" class="btn btn-sm btn-warning w-100">Place on hold</button>
                        </form>
                    @endif
                    @if(in_array($booking->status, ['on_hold','draft','pending'], true))
                        <form method="POST" action="{{ route('admin.bookings.confirm', $booking) }}" class="mb-2" onsubmit="return confirm('Confirm this booking?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success w-100">Confirm booking</button>
                        </form>
                    @endif
                    @if(!in_array($booking->status, ['cancelled'], true))
                        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" class="mb-3">
                            @csrf
                            <label class="form-label small">Cancel reason</label>
                            <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" required></textarea>
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Cancel booking</button>
                        </form>
                    @endif

                    <p class="small text-muted mb-1">Supplier hooks (placeholders)</p>
                    <p class="small mb-0">Flight: <code>{{ $booking->supplier_flight_hook_status ?? '—' }}</code><br>
                        Hotel: <code>{{ $booking->supplier_hotel_hook_status ?? '—' }}</code></p>
                    @if($booking->hold_expires_at)
                        <p class="small mt-2 mb-0">Hold expires: {{ $booking->hold_expires_at->format('d M Y H:i') }}</p>
                    @endif
                    @if($booking->invoice_number)
                        <p class="small mt-2 mb-0">Invoice: {{ $booking->invoice_number }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">Amend / notes</h2>
                    <form method="POST" action="{{ route('admin.bookings.amend', $booking) }}">
                        @csrf
                        @method('PATCH')
                        <label class="form-label small">Internal notes</label>
                        <textarea name="internal_notes" class="form-control form-control-sm mb-2" rows="2">{{ old('internal_notes', $booking->internal_notes) }}</textarea>
                        <label class="form-label small">Customer-facing remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2">{{ old('remarks', $booking->remarks) }}</textarea>
                        <label class="form-label small">Amend reason (audit)</label>
                        <input type="text" name="amend_reason" class="form-control form-control-sm mb-2" value="{{ old('amend_reason') }}">
                        <p class="small text-muted">To replace travelers, submit names via API or extend this form with repeated fields later.</p>
                        <button type="submit" class="btn btn-sm btn-primary">Save changes</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-2">Status history</h2>
                    <ul class="list-unstyled small mb-0">
                        @forelse($booking->statusHistories as $h)
                            <li class="mb-2 border-bottom pb-2">
                                <strong>{{ $h->event ?? $h->to_status }}</strong>
                                @if($h->from_status) <span class="text-muted">{{ $h->from_status }} → {{ $h->to_status }}</span> @endif
                                <br>
                                <span class="text-muted">{{ $h->created_at?->format('d M Y H:i') }} · {{ $h->user?->name ?? 'system' }}</span>
                                @if($h->reason)<br><em>{{ $h->reason }}</em>@endif
                            </li>
                        @empty
                            <li class="text-muted">No history.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
