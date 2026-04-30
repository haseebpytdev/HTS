<?php

namespace App\Services\Finance;

use App\Services\System\SystemSettingsService;

final class FinanceSettingsService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'payment_gateway_default' => $this->settings->getString('finance.gateway.default_driver', 'manual', ['scope' => 'platform', 'category' => 'finance']),
            'payment_gateway_timeout_seconds' => $this->settings->getInt('finance.gateway.timeout_seconds', 30, ['scope' => 'platform', 'category' => 'finance']),
            'payment_gateway_max_retries' => $this->settings->getInt('finance.gateway.max_retries', 1, ['scope' => 'platform', 'category' => 'finance']),
            'deposit_min_percent' => $this->settings->getFloat('finance.deposit.min_percent', 20.0, ['scope' => 'platform', 'category' => 'finance']),
            'deposit_min_amount' => $this->settings->getFloat('finance.deposit.min_amount', 0.0, ['scope' => 'platform', 'category' => 'finance']),
            'deposit_allow_balance_installments' => $this->settings->getBool('finance.deposit.allow_balance_installments', true, ['scope' => 'platform', 'category' => 'finance']),
            'refund_auto_approve_limit' => $this->settings->getFloat('finance.refund.auto_approve_limit', 0.0, ['scope' => 'platform', 'category' => 'finance']),
            'refund_reason_required' => $this->settings->getBool('finance.refund.reason_required', true, ['scope' => 'platform', 'category' => 'finance']),
            'refund_max_days_since_payment' => $this->settings->getInt('finance.refund.max_days_since_payment', 90, ['scope' => 'platform', 'category' => 'finance']),
            'wallet_credit_limit_default' => $this->settings->getFloat('finance.wallet.default_credit_limit', 0.0, ['scope' => 'platform', 'category' => 'finance']),
            'wallet_terms_days_default' => $this->settings->getInt('finance.wallet.default_terms_days', 0, ['scope' => 'platform', 'category' => 'finance']),
            'markup_mode_default' => $this->settings->getString('finance.markup.default_mode', 'percentage', ['scope' => 'platform', 'category' => 'finance']),
            'markup_value_default' => $this->settings->getFloat('finance.markup.default_value', 10.0, ['scope' => 'platform', 'category' => 'finance']),
            'tax_percent_default' => $this->settings->getFloat('finance.tax.default_percent', 0.0, ['scope' => 'platform', 'category' => 'finance']),
            'invoice_number_prefix' => $this->settings->getString('finance.invoice.number_prefix', 'INV-', ['scope' => 'platform', 'category' => 'finance']),
            'invoice_sequence_padding' => $this->settings->getInt('finance.invoice.sequence_padding', 8, ['scope' => 'platform', 'category' => 'finance']),
            'invoice_next_sequence' => max(1, $this->settings->getInt('finance.invoice.next_sequence', 1, ['scope' => 'platform', 'category' => 'finance'])),
            'allow_manual_ledger_adjustments' => $this->settings->getBool('finance.ledger.allow_manual_adjustments', false, ['scope' => 'platform', 'category' => 'finance']),
            'overdue_threshold_days' => $this->settings->getInt('finance.overdue.threshold_days', 0, ['scope' => 'platform', 'category' => 'finance']),
            'revenue_report_default_window_days' => $this->settings->getInt('finance.reports.default_window_days', 30, ['scope' => 'platform', 'category' => 'finance']),
            'revenue_report_include_cancelled' => $this->settings->getBool('finance.reports.include_cancelled', false, ['scope' => 'platform', 'category' => 'finance']),
            'net_margin_dashboard_enabled' => $this->settings->getBool('finance.dashboard.net_margin_enabled', true, ['scope' => 'platform', 'category' => 'finance']),
            'net_margin_alert_threshold_percent' => $this->settings->getFloat('finance.dashboard.net_margin_alert_threshold_percent', 8.0, ['scope' => 'platform', 'category' => 'finance']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $this->settings->setMany([
            'finance.gateway.default_driver' => (string) ($data['payment_gateway_default'] ?? 'manual'),
            'finance.gateway.timeout_seconds' => (int) ($data['payment_gateway_timeout_seconds'] ?? 30),
            'finance.gateway.max_retries' => (int) ($data['payment_gateway_max_retries'] ?? 1),
            'finance.deposit.min_percent' => (float) ($data['deposit_min_percent'] ?? 20),
            'finance.deposit.min_amount' => (float) ($data['deposit_min_amount'] ?? 0),
            'finance.deposit.allow_balance_installments' => (bool) ($data['deposit_allow_balance_installments'] ?? true),
            'finance.refund.auto_approve_limit' => (float) ($data['refund_auto_approve_limit'] ?? 0),
            'finance.refund.reason_required' => (bool) ($data['refund_reason_required'] ?? true),
            'finance.refund.max_days_since_payment' => (int) ($data['refund_max_days_since_payment'] ?? 90),
            'finance.wallet.default_credit_limit' => (float) ($data['wallet_credit_limit_default'] ?? 0),
            'finance.wallet.default_terms_days' => (int) ($data['wallet_terms_days_default'] ?? 0),
            'finance.markup.default_mode' => (string) ($data['markup_mode_default'] ?? 'percentage'),
            'finance.markup.default_value' => (float) ($data['markup_value_default'] ?? 10),
            'finance.tax.default_percent' => (float) ($data['tax_percent_default'] ?? 0),
            'finance.invoice.number_prefix' => (string) ($data['invoice_number_prefix'] ?? 'INV-'),
            'finance.invoice.sequence_padding' => (int) ($data['invoice_sequence_padding'] ?? 8),
            'finance.invoice.next_sequence' => max(1, (int) ($data['invoice_next_sequence'] ?? 1)),
            'finance.ledger.allow_manual_adjustments' => (bool) ($data['allow_manual_ledger_adjustments'] ?? false),
            'finance.overdue.threshold_days' => (int) ($data['overdue_threshold_days'] ?? 0),
            'finance.reports.default_window_days' => (int) ($data['revenue_report_default_window_days'] ?? 30),
            'finance.reports.include_cancelled' => (bool) ($data['revenue_report_include_cancelled'] ?? false),
            'finance.dashboard.net_margin_enabled' => (bool) ($data['net_margin_dashboard_enabled'] ?? true),
            'finance.dashboard.net_margin_alert_threshold_percent' => (float) ($data['net_margin_alert_threshold_percent'] ?? 8),
        ], ['scope' => 'platform', 'category' => 'finance']);
    }

    public function minimumDepositAmount(float $bookingTotal): float
    {
        $percent = $this->settings->getFloat('finance.deposit.min_percent', 20.0, ['scope' => 'platform', 'category' => 'finance']);
        $percent = max(0.0, min($percent, 100.0));
        $minAmount = max(0.0, $this->settings->getFloat('finance.deposit.min_amount', 0.0, ['scope' => 'platform', 'category' => 'finance']));
        $percentAmount = round(($bookingTotal * $percent) / 100, 2);

        return max($percentAmount, $minAmount);
    }

    public function isManualLedgerAdjustmentAllowed(): bool
    {
        return $this->settings->getBool('finance.ledger.allow_manual_adjustments', false, ['scope' => 'platform', 'category' => 'finance']);
    }

    public function isRefundReasonRequired(): bool
    {
        return $this->settings->getBool('finance.refund.reason_required', true, ['scope' => 'platform', 'category' => 'finance']);
    }

    public function refundMaxDaysSincePayment(): int
    {
        return max(0, $this->settings->getInt('finance.refund.max_days_since_payment', 90, ['scope' => 'platform', 'category' => 'finance']));
    }

    public function reportDefaultWindowDays(): int
    {
        return max(1, $this->settings->getInt('finance.reports.default_window_days', 30, ['scope' => 'platform', 'category' => 'finance']));
    }

    public function reportIncludesCancelledBookings(): bool
    {
        return $this->settings->getBool('finance.reports.include_cancelled', false, ['scope' => 'platform', 'category' => 'finance']);
    }

    public function netMarginDashboardEnabled(): bool
    {
        return $this->settings->getBool('finance.dashboard.net_margin_enabled', true, ['scope' => 'platform', 'category' => 'finance']);
    }

    public function netMarginAlertThresholdPercent(): float
    {
        return max(0.0, $this->settings->getFloat('finance.dashboard.net_margin_alert_threshold_percent', 8.0, ['scope' => 'platform', 'category' => 'finance']));
    }

    public function walletDefaultTermsDays(): int
    {
        return max(0, $this->settings->getInt('finance.wallet.default_terms_days', 0, ['scope' => 'platform', 'category' => 'finance']));
    }

    public function nextInvoiceNumber(): string
    {
        $prefix = (string) $this->settings->getString('finance.invoice.number_prefix', 'INV-', ['scope' => 'platform', 'category' => 'finance']);
        $padding = max(3, min(12, $this->settings->getInt('finance.invoice.sequence_padding', 8, ['scope' => 'platform', 'category' => 'finance'])));
        $next = max(1, $this->settings->getInt('finance.invoice.next_sequence', 1, ['scope' => 'platform', 'category' => 'finance']));

        $invoice = $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);

        $this->settings->set('finance.invoice.next_sequence', $next + 1, ['scope' => 'platform', 'category' => 'finance']);

        return $invoice;
    }
}
