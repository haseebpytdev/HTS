<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.general.site_name' => ['required', 'string', 'max:120'],
            'settings.general.support_email' => ['required', 'email', 'max:190'],
            'settings.branding.primary_color' => ['required', 'string', 'max:20'],
            'settings.branding.logo_url' => ['nullable', 'url', 'max:1000'],
            'settings.localization.default_currency' => ['required', 'string', 'size:3'],
            'settings.localization.default_locale' => ['required', Rule::in(['en', 'ar'])],
            'settings.booking.auto_hold_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.booking.hold_expiry_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'settings.finance.default_markup_percent' => ['required', 'numeric', 'min:0', 'max:500'],
            'settings.finance.allow_negative_margin' => ['required', Rule::in(['0', '1'])],
            'settings.tax.default_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings.pricing.markup_mode' => ['required', Rule::in(['fixed', 'percentage'])],
            'settings.pricing.markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'settings.payments.default_gateway' => ['required', Rule::in(['manual', 'stripe'])],
            'settings.payments.wallet_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.integrations.default_provider' => ['required', Rule::in([AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'sabre', 'travelport', 'iati', 'duffel', 'stub'])],
            'settings.integrations.fallback_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.notifications.email_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.notifications.sms_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.approval_rules.refund_requires_approval' => ['required', Rule::in(['0', '1'])],
            'settings.approval_rules.tenancy_toggle_requires_approval' => ['required', Rule::in(['0', '1'])],
            'settings.role_permission_matrix.proposal_mode_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.tenancy.tenant_scoping_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.feature_flags.quote_auto_save' => ['required', Rule::in(['0', '1'])],
            'settings.feature_flags.require_booking_approval' => ['required', Rule::in(['0', '1'])],
            'settings.feature_flags.group_ticketing_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.feature_flags.umrah_packages_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.security_document_rules.document_rescan_enabled' => ['required', Rule::in(['0', '1'])],
            'settings.security_document_rules.integration_api_idempotency_required' => ['required', Rule::in(['0', '1'])],
            'settings.seo_cms_defaults.default_meta_title_suffix' => ['required', 'string', 'max:120'],
            'settings.seo_cms_defaults.homepage_indexable' => ['required', Rule::in(['0', '1'])],
        ];
    }
}
