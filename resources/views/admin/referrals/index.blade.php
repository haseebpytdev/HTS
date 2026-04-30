@extends('layouts.admin')

@section('title', 'Referrals')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Referrals</h1>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.referrals.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">Generate code for customer</label>
                    <select name="referrer_customer_id" class="form-select form-select-sm" required>
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ trim($customer->first_name.' '.$customer->last_name) ?: $customer->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-sm btn-primary">Generate referral code</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Code</th><th>Referrer</th><th>Referred</th><th>Status</th><th>Reward</th></tr></thead>
                <tbody>
                @forelse($referrals as $referral)
                    <tr>
                        <td><code>{{ $referral->referral_code }}</code></td>
                        <td>{{ $referral->referrer?->fullName() ?? $referral->referrer?->email ?? '-' }}</td>
                        <td>{{ $referral->referredCustomer?->fullName() ?? $referral->referredCustomer?->email ?? '-' }}</td>
                        <td>{{ ucfirst($referral->status) }}</td>
                        <td>{{ $referral->reward_currency }} {{ number_format((float) ($referral->reward_amount ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No referrals yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $referrals->links() }}</div>
    </div>
@endsection
