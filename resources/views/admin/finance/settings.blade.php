@extends('layouts.admin')

@section('title', 'Finance Settings')

@section('admin-content')
    <h1 class="h4 mb-3">Super Admin Finance Controls</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.finance.settings.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="allow_manual_ledger_adjustments" value="0">
        <input type="hidden" name="net_margin_dashboard_enabled" value="0">
        <input type="hidden" name="deposit_allow_balance_installments" value="0">
        <input type="hidden" name="refund_reason_required" value="0">
        <input type="hidden" name="revenue_report_include_cancelled" value="0">

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Gateway, Deposits, Refunds, Wallet</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">Payment Gateway</label>
                    <select name="payment_gateway_default" class="form-select">
                        <option value="manual" @selected(($settings['payment_gateway_default'] ?? 'manual') === 'manual')>Manual</option>
                        <option value="stripe" @selected(($settings['payment_gateway_default'] ?? '') === 'stripe')>Stripe</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Gateway Timeout (sec)</label><input class="form-control" type="number" name="payment_gateway_timeout_seconds" value="{{ $settings['payment_gateway_timeout_seconds'] ?? 30 }}"></div>
                <div class="col-md-3"><label class="form-label">Gateway Max Retries</label><input class="form-control" type="number" name="payment_gateway_max_retries" value="{{ $settings['payment_gateway_max_retries'] ?? 1 }}"></div>
                <div class="col-md-3"><label class="form-label">Deposit Min %</label><input class="form-control" type="number" step="0.01" name="deposit_min_percent" value="{{ $settings['deposit_min_percent'] ?? 20 }}"></div>
                <div class="col-md-3"><label class="form-label">Deposit Minimum Amount</label><input class="form-control" type="number" step="0.01" name="deposit_min_amount" value="{{ $settings['deposit_min_amount'] ?? 0 }}"></div>
                <div class="col-md-3"><label class="form-label">Refund Auto-Approve Limit</label><input class="form-control" type="number" step="0.01" name="refund_auto_approve_limit" value="{{ $settings['refund_auto_approve_limit'] ?? 0 }}"></div>
                <div class="col-md-3"><label class="form-label">Refund Max Age (days)</label><input class="form-control" type="number" name="refund_max_days_since_payment" value="{{ $settings['refund_max_days_since_payment'] ?? 90 }}"></div>
                <div class="col-md-3"><label class="form-label">Wallet Credit Default</label><input class="form-control" type="number" step="0.01" name="wallet_credit_limit_default" value="{{ $settings['wallet_credit_limit_default'] ?? 0 }}"></div>
                <div class="col-md-3"><label class="form-label">Wallet Terms Days Default</label><input class="form-control" type="number" name="wallet_terms_days_default" value="{{ $settings['wallet_terms_days_default'] ?? 0 }}"></div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="deposit_allow_balance_installments" id="deposit_allow_balance_installments" @checked((bool) ($settings['deposit_allow_balance_installments'] ?? true))>
                        <label class="form-check-label" for="deposit_allow_balance_installments">Allow balance installments</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="refund_reason_required" id="refund_reason_required" @checked((bool) ($settings['refund_reason_required'] ?? true))>
                        <label class="form-check-label" for="refund_reason_required">Refund reason required</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="allow_manual_ledger_adjustments" id="allow_manual_ledger_adjustments" @checked((bool) ($settings['allow_manual_ledger_adjustments'] ?? false))>
                        <label class="form-check-label" for="allow_manual_ledger_adjustments">Allow manual ledger adjustments</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Markup, Tax, Invoice, Overdue and Reporting</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">Markup Mode Default</label>
                    <select name="markup_mode_default" class="form-select">
                        <option value="percentage" @selected(($settings['markup_mode_default'] ?? 'percentage') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(($settings['markup_mode_default'] ?? '') === 'fixed')>Fixed</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Markup Value Default</label><input class="form-control" type="number" step="0.01" name="markup_value_default" value="{{ $settings['markup_value_default'] ?? 10 }}"></div>
                <div class="col-md-3"><label class="form-label">Tax Default %</label><input class="form-control" type="number" step="0.01" name="tax_percent_default" value="{{ $settings['tax_percent_default'] ?? 0 }}"></div>
                <div class="col-md-3"><label class="form-label">Overdue Threshold Days</label><input class="form-control" type="number" name="overdue_threshold_days" value="{{ $settings['overdue_threshold_days'] ?? 0 }}"></div>

                <div class="col-md-3"><label class="form-label">Invoice Prefix</label><input class="form-control" name="invoice_number_prefix" value="{{ $settings['invoice_number_prefix'] ?? 'INV-' }}"></div>
                <div class="col-md-3"><label class="form-label">Invoice Sequence Padding</label><input class="form-control" type="number" name="invoice_sequence_padding" value="{{ $settings['invoice_sequence_padding'] ?? 8 }}"></div>
                <div class="col-md-3"><label class="form-label">Invoice Next Sequence</label><input class="form-control" type="number" name="invoice_next_sequence" value="{{ $settings['invoice_next_sequence'] ?? 1 }}"></div>
                <div class="col-md-3"><label class="form-label">Revenue Report Window Days</label><input class="form-control" type="number" name="revenue_report_default_window_days" value="{{ $settings['revenue_report_default_window_days'] ?? 30 }}"></div>
                <div class="col-md-3"><label class="form-label">Net Margin Alert Threshold %</label><input class="form-control" type="number" step="0.01" name="net_margin_alert_threshold_percent" value="{{ $settings['net_margin_alert_threshold_percent'] ?? 8 }}"></div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="revenue_report_include_cancelled" id="revenue_report_include_cancelled" @checked((bool) ($settings['revenue_report_include_cancelled'] ?? false))>
                        <label class="form-check-label" for="revenue_report_include_cancelled">Include cancelled in revenue reports</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="net_margin_dashboard_enabled" id="net_margin_dashboard_enabled" @checked((bool) ($settings['net_margin_dashboard_enabled'] ?? true))>
                        <label class="form-check-label" for="net_margin_dashboard_enabled">Enable net-margin dashboard controls</label>
                    </div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button type="submit" class="btn btn-primary">Save Finance Settings</button>
    </form>
@endsection
