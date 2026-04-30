@extends('layouts.admin')

@section('title', $sectionTitle)

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">{{ $sectionTitle }}</h1>
        <small class="text-muted">Dedicated settings page for this subsection.</small>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($sectionMap as $key => $meta)
                    <a href="{{ route('admin.settings.section', ['section' => $key]) }}"
                       class="btn btn-sm {{ $section === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $meta['title'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update', ['section' => $section]) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            @if($section === 'general')
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm"><div class="card-body">
                        <label class="form-label small">Site name</label>
                        <input type="text" name="settings[general][site_name]" class="form-control mb-2" value="{{ $settings['general']['site_name'] ?? '' }}">
                        <label class="form-label small">Support email</label>
                        <input type="email" name="settings[general][support_email]" class="form-control" value="{{ $settings['general']['support_email'] ?? '' }}">
                    </div></div>
                </div>
            @elseif($section === 'branding')
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm"><div class="card-body">
                        <label class="form-label small">Primary color</label>
                        <input type="text" name="settings[branding][primary_color]" class="form-control mb-2" value="{{ $settings['branding']['primary_color'] ?? '#0d6efd' }}">
                        <label class="form-label small">Logo URL</label>
                        <input type="url" name="settings[branding][logo_url]" class="form-control" value="{{ $settings['branding']['logo_url'] ?? '' }}">
                    </div></div>
                </div>
            @elseif($section === 'localization')
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm"><div class="card-body">
                        <label class="form-label small">Default currency</label>
                        <input type="text" name="settings[localization][default_currency]" class="form-control mb-2" maxlength="3" value="{{ $settings['localization']['default_currency'] ?? 'PKR' }}">
                        <label class="form-label small">Default language</label>
                        <select name="settings[localization][default_locale]" class="form-select">
                            <option value="en" @selected(($settings['localization']['default_locale'] ?? 'en') === 'en')>English</option>
                            <option value="ar" @selected(($settings['localization']['default_locale'] ?? 'en') === 'ar')>Arabic</option>
                        </select>
                    </div></div>
                </div>
            @elseif($section === 'integrations')
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm"><div class="card-body">
                        <label class="form-label small">Default provider</label>
                        <select name="settings[integrations][default_provider]" class="form-select mb-2">
                            @php($defaultProvider = (string) ($settings['integrations']['default_provider'] ?? \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE))
                            @foreach([
                                \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE => 'AMADEUS SELF SERVICE',
                                'sabre' => 'SABRE',
                                'travelport' => 'TRAVELPORT',
                                'iati' => 'IATI',
                                'duffel' => 'DUFFEL',
                                'stub' => 'STUB',
                            ] as $provider => $label)
                                @php($isAmadeusSelfServiceOption = $provider === \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE)
                                @php($isSelected = $isAmadeusSelfServiceOption
                                    ? in_array($defaultProvider, [\App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE, \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::LEGACY_CODE], true)
                                    : $defaultProvider === $provider)
                                <option value="{{ $provider }}" @selected($isSelected)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="form-label small">Fallback enabled</label>
                        <select name="settings[integrations][fallback_enabled]" class="form-select mb-2">
                            <option value="1" @selected(($settings['integrations']['fallback_enabled'] ?? '1') === '1')>Enabled</option>
                            <option value="0" @selected(($settings['integrations']['fallback_enabled'] ?? '1') === '0')>Disabled</option>
                        </select>
                        <a href="{{ route('admin.integrations.index') }}" class="btn btn-sm btn-outline-secondary">Open provider configuration</a>
                    </div></div>
                </div>
            @elseif($section === 'tax_pricing')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Default tax percent</label>
                    <input type="number" step="0.01" min="0" max="100" name="settings[tax][default_tax_percent]" class="form-control mb-2" value="{{ $settings['tax']['default_tax_percent'] ?? '0' }}">
                    <label class="form-label small">Markup mode</label>
                    <select name="settings[pricing][markup_mode]" class="form-select mb-2">
                        <option value="percentage" @selected(($settings['pricing']['markup_mode'] ?? 'percentage') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(($settings['pricing']['markup_mode'] ?? 'percentage') === 'fixed')>Fixed</option>
                    </select>
                    <label class="form-label small">Markup value</label>
                    <input type="number" step="0.01" min="0" name="settings[pricing][markup_value]" class="form-control mb-2" value="{{ $settings['pricing']['markup_value'] ?? '10' }}">
                    <label class="form-label small">Default markup percent</label>
                    <input type="number" step="0.01" min="0" name="settings[finance][default_markup_percent]" class="form-control mb-2" value="{{ $settings['finance']['default_markup_percent'] ?? '10' }}">
                    <label class="form-label small">Allow negative margin</label>
                    <select name="settings[finance][allow_negative_margin]" class="form-select">
                        <option value="0" @selected(($settings['finance']['allow_negative_margin'] ?? '0') === '0')>No</option>
                        <option value="1" @selected(($settings['finance']['allow_negative_margin'] ?? '0') === '1')>Yes</option>
                    </select>
                </div></div></div>
            @elseif($section === 'booking_rules')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Auto hold enabled</label>
                    <select name="settings[booking][auto_hold_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['booking']['auto_hold_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['booking']['auto_hold_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Hold expiry minutes</label>
                    <input type="number" name="settings[booking][hold_expiry_minutes]" class="form-control" min="1" value="{{ $settings['booking']['hold_expiry_minutes'] ?? '30' }}">
                </div></div></div>
            @elseif($section === 'payments')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Default gateway</label>
                    <select name="settings[payments][default_gateway]" class="form-select mb-2">
                        <option value="manual" @selected(($settings['payments']['default_gateway'] ?? 'manual') === 'manual')>Manual</option>
                        <option value="stripe" @selected(($settings['payments']['default_gateway'] ?? 'manual') === 'stripe')>Stripe</option>
                    </select>
                    <label class="form-label small">Agency wallet enabled</label>
                    <select name="settings[payments][wallet_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['payments']['wallet_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['payments']['wallet_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Deposit min percent</label>
                    <input type="number" step="0.01" min="0" max="100" name="settings[payments][deposit_min_percent]" class="form-control mb-2" value="{{ $settings['payments']['deposit_min_percent'] ?? '20' }}">
                    <label class="form-label small">Refund auto-approve threshold</label>
                    <input type="number" step="0.01" min="0" name="settings[payments][refund_auto_approve_limit]" class="form-control mb-2" value="{{ $settings['payments']['refund_auto_approve_limit'] ?? '0' }}">
                    <label class="form-label small">Wallet overdraft limit</label>
                    <input type="number" step="0.01" min="0" name="settings[payments][wallet_overdraft_limit]" class="form-control" value="{{ $settings['payments']['wallet_overdraft_limit'] ?? '0' }}">
                </div></div></div>
            @elseif($section === 'notifications')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Email notifications</label>
                    <select name="settings[notifications][email_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['notifications']['email_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['notifications']['email_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">SMS notifications</label>
                    <select name="settings[notifications][sms_enabled]" class="form-select">
                        <option value="1" @selected(($settings['notifications']['sms_enabled'] ?? '0') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['notifications']['sms_enabled'] ?? '0') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'approvals')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Refund requires approval</label>
                    <select name="settings[approval_rules][refund_requires_approval]" class="form-select mb-2">
                        <option value="1" @selected(($settings['approval_rules']['refund_requires_approval'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['approval_rules']['refund_requires_approval'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Tenancy toggle requires approval</label>
                    <select name="settings[approval_rules][tenancy_toggle_requires_approval]" class="form-select">
                        <option value="1" @selected(($settings['approval_rules']['tenancy_toggle_requires_approval'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['approval_rules']['tenancy_toggle_requires_approval'] ?? '1') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'role_matrix')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Proposal mode enabled</label>
                    <select name="settings[role_permission_matrix][proposal_mode_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['role_permission_matrix']['proposal_mode_enabled'] ?? '0') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['role_permission_matrix']['proposal_mode_enabled'] ?? '0') === '0')>Disabled</option>
                    </select>
                    <a href="{{ route('admin.system.permissions.index') }}" class="btn btn-sm btn-outline-secondary">Open permission matrix</a>
                </div></div></div>
            @elseif($section === 'tenants')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Tenant scoping enabled</label>
                    <select name="settings[tenancy][tenant_scoping_enabled]" class="form-select">
                        <option value="1" @selected(($settings['tenancy']['tenant_scoping_enabled'] ?? '0') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['tenancy']['tenant_scoping_enabled'] ?? '0') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'feature_flags')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Quote auto save</label>
                    <select name="settings[feature_flags][quote_auto_save]" class="form-select mb-2">
                        <option value="1" @selected(($settings['feature_flags']['quote_auto_save'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['feature_flags']['quote_auto_save'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Require booking approval</label>
                    <select name="settings[feature_flags][require_booking_approval]" class="form-select">
                        <option value="1" @selected(($settings['feature_flags']['require_booking_approval'] ?? '0') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['feature_flags']['require_booking_approval'] ?? '0') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small mt-3">Group Ticketing (Our Service)</label>
                    <select name="settings[feature_flags][group_ticketing_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['feature_flags']['group_ticketing_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['feature_flags']['group_ticketing_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Umrah Packages (Our Service)</label>
                    <select name="settings[feature_flags][umrah_packages_enabled]" class="form-select">
                        <option value="1" @selected(($settings['feature_flags']['umrah_packages_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['feature_flags']['umrah_packages_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'security_documents')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Document rescan enabled</label>
                    <select name="settings[security_document_rules][document_rescan_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['security_document_rules']['document_rescan_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['security_document_rules']['document_rescan_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Integration idempotency required</label>
                    <select name="settings[security_document_rules][integration_api_idempotency_required]" class="form-select">
                        <option value="1" @selected(($settings['security_document_rules']['integration_api_idempotency_required'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['security_document_rules']['integration_api_idempotency_required'] ?? '1') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'seo_cms')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Default meta title suffix</label>
                    <input type="text" name="settings[seo_cms_defaults][default_meta_title_suffix]" class="form-control mb-2" value="{{ $settings['seo_cms_defaults']['default_meta_title_suffix'] ?? (' | '.config('brand.name')) }}">
                    <label class="form-label small">Homepage indexable</label>
                    <select name="settings[seo_cms_defaults][homepage_indexable]" class="form-select mb-2">
                        <option value="1" @selected(($settings['seo_cms_defaults']['homepage_indexable'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['seo_cms_defaults']['homepage_indexable'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Default package visibility</label>
                    <select name="settings[seo_cms_defaults][package_visibility_default]" class="form-select mb-2">
                        <option value="visible" @selected(($settings['seo_cms_defaults']['package_visibility_default'] ?? 'visible') === 'visible')>Visible</option>
                        <option value="hidden" @selected(($settings['seo_cms_defaults']['package_visibility_default'] ?? 'visible') === 'hidden')>Hidden</option>
                    </select>
                    <label class="form-label small">Default group visibility</label>
                    <select name="settings[seo_cms_defaults][group_visibility_default]" class="form-select mb-2">
                        <option value="visible" @selected(($settings['seo_cms_defaults']['group_visibility_default'] ?? 'visible') === 'visible')>Visible</option>
                        <option value="hidden" @selected(($settings['seo_cms_defaults']['group_visibility_default'] ?? 'visible') === 'hidden')>Hidden</option>
                    </select>
                    <label class="form-label small">Homepage banners</label>
                    <select name="settings[seo_cms_defaults][homepage_banners_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['seo_cms_defaults']['homepage_banners_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['seo_cms_defaults']['homepage_banners_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Landing pages</label>
                    <select name="settings[seo_cms_defaults][landing_pages_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['seo_cms_defaults']['landing_pages_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['seo_cms_defaults']['landing_pages_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Blog module</label>
                    <select name="settings[seo_cms_defaults][blog_enabled]" class="form-select">
                        <option value="1" @selected(($settings['seo_cms_defaults']['blog_enabled'] ?? '1') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['seo_cms_defaults']['blog_enabled'] ?? '1') === '0')>Disabled</option>
                    </select>
                </div></div></div>
            @elseif($section === 'maintenance')
                <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
                    <label class="form-label small">Maintenance mode enabled</label>
                    <select name="settings[maintenance_flags][maintenance_enabled]" class="form-select mb-2">
                        <option value="1" @selected(($settings['maintenance_flags']['maintenance_enabled'] ?? '0') === '1')>Enabled</option>
                        <option value="0" @selected(($settings['maintenance_flags']['maintenance_enabled'] ?? '0') === '0')>Disabled</option>
                    </select>
                    <label class="form-label small">Maintenance banner text</label>
                    <input type="text" name="settings[maintenance_flags][maintenance_banner_text]" class="form-control" value="{{ $settings['maintenance_flags']['maintenance_banner_text'] ?? '' }}">
                </div></div></div>
            @endif
        </div>

        @if($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-3">
            <button class="btn btn-primary" type="submit">Save {{ $sectionTitle }}</button>
        </div>
    </form>
@endsection
