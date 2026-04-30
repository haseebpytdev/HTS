<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsSectionRequest;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settingsService,
    ) {
    }

    private const KEY_MAP = [
        'general' => [
            'site_name' => 'app.general.site_name',
            'support_email' => 'app.general.support_email',
        ],
        'branding' => [
            'primary_color' => 'app.branding.primary_color',
            'logo_url' => 'app.branding.logo_url',
        ],
        'localization' => [
            'default_currency' => 'app.localization.default_currency',
            'default_locale' => 'app.localization.default_locale',
        ],
        'booking' => [
            'auto_hold_enabled' => 'booking.auto_hold_enabled',
            'hold_expiry_minutes' => 'booking.hold_expiry_minutes',
        ],
        'finance' => [
            'default_markup_percent' => 'finance.default_markup_percent',
            'allow_negative_margin' => 'finance.allow_negative_margin',
        ],
        'tax' => [
            'default_tax_percent' => 'tax.default_percent',
        ],
        'pricing' => [
            'markup_mode' => 'pricing.markup_mode',
            'markup_value' => 'pricing.markup_value',
        ],
        'payments' => [
            'default_gateway' => 'payments.default_gateway',
            'wallet_enabled' => 'payments.wallet_enabled',
            'deposit_min_percent' => 'payments.deposit_min_percent',
            'refund_auto_approve_limit' => 'payments.refund_auto_approve_limit',
            'wallet_overdraft_limit' => 'payments.wallet_overdraft_limit',
        ],
        'integrations' => [
            'default_provider' => 'integrations.default_provider',
            'fallback_enabled' => 'integrations.fallback_enabled',
        ],
        'notifications' => [
            'email_enabled' => 'notifications.email_enabled',
            'sms_enabled' => 'notifications.sms_enabled',
        ],
        'approval_rules' => [
            'refund_requires_approval' => 'approval.payment_refund_required',
            'tenancy_toggle_requires_approval' => 'approval.tenancy_toggle_required',
        ],
        'role_permission_matrix' => [
            'proposal_mode_enabled' => 'permissions.proposal_mode_enabled',
        ],
        'tenancy' => [
            'tenant_scoping_enabled' => 'tenancy.tenant_scoping_enabled',
        ],
        'feature_flags' => [
            'quote_auto_save' => 'app.feature_flags.quote_auto_save',
            'require_booking_approval' => 'app.feature_flags.require_booking_approval',
            'group_ticketing_enabled' => 'app.feature_flags.group_ticketing_enabled',
            'umrah_packages_enabled' => 'app.feature_flags.umrah_packages_enabled',
        ],
        'security_document_rules' => [
            'document_rescan_enabled' => 'documents.rescan_enabled',
            'integration_api_idempotency_required' => 'integrations.idempotency_required',
        ],
        'seo_cms_defaults' => [
            'default_meta_title_suffix' => 'seo.default_meta_title_suffix',
            'homepage_indexable' => 'seo.homepage_indexable',
            'package_visibility_default' => 'cms.package_visibility_default',
            'group_visibility_default' => 'cms.group_visibility_default',
            'homepage_banners_enabled' => 'cms.homepage_banners_enabled',
            'landing_pages_enabled' => 'cms.landing_pages_enabled',
            'blog_enabled' => 'cms.blog_enabled',
        ],
        'maintenance_flags' => [
            'maintenance_enabled' => 'system.maintenance_enabled',
            'maintenance_banner_text' => 'system.maintenance_banner_text',
        ],
    ];

    /**
     * @return array<string, array{title: string, groups: array<int, string>}>
     */
    private const SECTIONS = [
        'general' => ['title' => 'General Settings', 'groups' => ['general']],
        'branding' => ['title' => 'Branding', 'groups' => ['branding']],
        'localization' => ['title' => 'Currency & Localization', 'groups' => ['localization']],
        'integrations' => ['title' => 'Integrations / Providers', 'groups' => ['integrations']],
        'tax_pricing' => ['title' => 'Tax Rules + Pricing / Markups', 'groups' => ['tax', 'pricing', 'finance']],
        'booking_rules' => ['title' => 'Booking Rules', 'groups' => ['booking']],
        'payments' => ['title' => 'Payment Settings', 'groups' => ['payments']],
        'notifications' => ['title' => 'Notification Settings', 'groups' => ['notifications']],
        'approvals' => ['title' => 'Approval Rules', 'groups' => ['approval_rules']],
        'role_matrix' => ['title' => 'Role/Permission Matrix', 'groups' => ['role_permission_matrix']],
        'tenants' => ['title' => 'Tenant Settings', 'groups' => ['tenancy']],
        'feature_flags' => ['title' => 'Feature Flags', 'groups' => ['feature_flags']],
        'security_documents' => ['title' => 'Security / Document Rules', 'groups' => ['security_document_rules']],
        'seo_cms' => ['title' => 'SEO / CMS Defaults', 'groups' => ['seo_cms_defaults']],
        'maintenance' => ['title' => 'Maintenance Flags', 'groups' => ['maintenance_flags']],
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.section', ['section' => 'general']);
    }

    public function section(string $section): View
    {
        $this->authorize('manage-tenancy-settings');
        $section = $this->resolveSection($section);

        $settings = [];
        foreach ($this->groupsForSection($section) as $category) {
            $keys = Arr::get(self::KEY_MAP, $category, []);
            foreach ($keys as $uiKey => $storageKey) {
                $settings[$category][$uiKey] = $this->settingsService->getString(
                    key: $storageKey,
                    default: $this->defaultValue($storageKey),
                    context: ['scope' => 'platform', 'category' => $this->categoryForStorageKey($storageKey)]
                );
            }
        }

        return view('admin.settings.section', [
            'settings' => $settings,
            'section' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionMap' => self::SECTIONS,
        ]);
    }

    public function update(UpdateSettingsSectionRequest $request, string $section): RedirectResponse
    {
        $this->authorize('manage-tenancy-settings');
        $section = $this->resolveSection($section);
        $validated = $request->validated();

        foreach ($this->groupsForSection($section) as $category) {
            $mapping = Arr::get(self::KEY_MAP, $category, []);
            foreach ($mapping as $uiKey => $storageKey) {
                $value = data_get($validated, "settings.$category.$uiKey");
                $this->settingsService->set(
                    key: $storageKey,
                    value: $value,
                    context: ['scope' => 'platform', 'category' => $this->categoryForStorageKey($storageKey)]
                );
            }
        }

        return redirect()
            ->route('admin.settings.section', ['section' => $section])
            ->with('success', self::SECTIONS[$section]['title'].' updated successfully.');
    }

    private function resolveSection(string $section): string
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        return $section;
    }

    /**
     * @return array<int, string>
     */
    private function groupsForSection(string $section): array
    {
        return self::SECTIONS[$section]['groups'];
    }

    private function defaultValue(string $key): ?string
    {
        return match ($key) {
            'booking.auto_hold_enabled',
            'integrations.fallback_enabled',
            'notifications.email_enabled',
            'app.feature_flags.quote_auto_save' => '1',
            'finance.allow_negative_margin',
            'notifications.sms_enabled',
            'app.feature_flags.require_booking_approval' => '0',
            'app.feature_flags.group_ticketing_enabled',
            'app.feature_flags.umrah_packages_enabled',
            'approval.payment_refund_required',
            'approval.tenancy_toggle_required',
            'permissions.proposal_mode_enabled',
            'documents.rescan_enabled',
            'integrations.idempotency_required',
            'seo.homepage_indexable',
            'payments.wallet_enabled' => '1',
            'cms.homepage_banners_enabled',
            'cms.landing_pages_enabled',
            'cms.blog_enabled' => '1',
            'system.maintenance_enabled' => '0',
            'booking.hold_expiry_minutes' => '30',
            'finance.default_markup_percent' => '10',
            'tax.default_percent' => '0',
            'pricing.markup_mode' => 'percentage',
            'pricing.markup_value' => '10',
            'integrations.default_provider' => AmadeusSelfServiceProvider::CODE,
            'payments.default_gateway' => 'manual',
            'payments.deposit_min_percent' => '20',
            'payments.refund_auto_approve_limit' => '0',
            'payments.wallet_overdraft_limit' => '0',
            'tenancy.tenant_scoping_enabled' => $this->settingsService->getString(
                key: 'tenancy.tenant_scoping_enabled',
                default: '0',
                context: ['scope' => 'platform', 'category' => 'tenancy'],
                configFallbackKey: 'tenancy.tenant_scoping_enabled'
            ),
            'app.general.site_name' => config('app.name', config('brand.name')),
            'app.general.support_email' => config('brand.support_email', 'support@example.com'),
            'app.branding.primary_color' => '#0d6efd',
            'app.branding.logo_url' => '',
            'app.localization.default_currency' => 'PKR',
            'app.localization.default_locale' => 'en',
            'seo.default_meta_title_suffix' => ' | '.config('brand.name'),
            'cms.package_visibility_default' => 'visible',
            'cms.group_visibility_default' => 'visible',
            'system.maintenance_banner_text' => 'System maintenance in progress. Some operations may be delayed.',
            default => null,
        };
    }

    private function categoryForStorageKey(string $storageKey): string
    {
        $parts = explode('.', trim($storageKey), 2);
        $first = trim((string) ($parts[0] ?? ''));

        return $first !== '' ? $first : 'general';
    }
}
