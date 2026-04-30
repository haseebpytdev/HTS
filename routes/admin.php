<?php

use App\Http\Controllers\Admin\CmsCatalogController;
use App\Http\Controllers\Admin\ContentBlockController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ExportHistoryController;
use App\Http\Controllers\Admin\GroupGalleryController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\InquiryCrmController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PackageGalleryController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\QuotationController;
use App\Http\Controllers\Admin\AgencyWalletPaymentController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingDocumentController;
use App\Http\Controllers\Admin\BookingPaymentController;
use App\Http\Controllers\Admin\PaymentRefundController;
use App\Http\Controllers\Admin\SeoPageController;
use App\Http\Controllers\Admin\TenancySettingsController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\LandingPageController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ModuleCatalogController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ModuleCredentialController;
use App\Http\Controllers\Admin\ModuleHealthCheckController;
use App\Http\Controllers\Admin\ModulePricingController;
use App\Http\Controllers\Admin\ModuleTaxController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\IntegrationSupplierAccountController;
use App\Http\Controllers\Admin\TenantIntegrationPolicyController;
use App\Http\Controllers\Admin\IntegrationProviderController;
use App\Http\Controllers\Admin\IntegrationConnectionController;
use App\Http\Controllers\Admin\IntegrationConnectionTestController;
use App\Http\Controllers\Admin\TenantProviderAccessController;
use App\Http\Controllers\Admin\TenantPlanController;
use App\Http\Controllers\Admin\TenantModuleAccessController;
use App\Http\Controllers\Admin\HealthMonitoringController;
use App\Http\Controllers\Admin\CmsSettingsController;
use App\Http\Controllers\Admin\FinanceSettingsController;
use App\Http\Controllers\Admin\DocumentSecuritySettingsController;
use App\Http\Controllers\Admin\AirportDirectoryController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\FlightEntryController;
use App\Http\Controllers\Admin\FlightSearchResultController;
use App\Http\Controllers\Admin\HotelRateController;
use App\Http\Controllers\Admin\HotelRoomTypeController;
use App\Http\Controllers\Admin\TransportRateController;
use App\Http\Controllers\Admin\TransportTypeController;
use App\Http\Controllers\Admin\VisaRateController;
use App\Http\Controllers\Admin\VisaTypeController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,admin,sales_operator', 'compliance.audit'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:module.dashboard.view')
        ->name('dashboard');
    Route::get('/operations-center', [DashboardController::class, 'index'])
        ->middleware('permission:module.dashboard.view')
        ->name('operations-center');
    Route::get('/operations-center/failed-tasks', function (): RedirectResponse {
        return redirect()->route('admin.monitoring.failed-jobs-alerts');
    })
        ->middleware('permission:module.dashboard.view')
        ->name('operations-center.failed-tasks');
    Route::get('/analytics', [AnalyticsController::class, 'index'])
        ->middleware('permission:module.analytics.view')
        ->name('analytics.index');
    Route::get('/analytics/agencies.csv', [AnalyticsController::class, 'agenciesCsv'])
        ->middleware('permission:module.analytics.view')
        ->name('analytics.agencies-csv');
    Route::get('/analytics/packages.csv', [AnalyticsController::class, 'packagesCsv'])
        ->middleware('permission:module.analytics.view')
        ->name('analytics.packages-csv');
    Route::post('/analytics/queue-bundle-csv', [AnalyticsController::class, 'queueBundleCsv'])
        ->middleware('permission:module.analytics.view')
        ->name('analytics.queue-bundle-csv');
    Route::get('/analytics/task-reports/{taskRun}/download', [AnalyticsController::class, 'downloadTaskReport'])
        ->middleware('permission:module.analytics.view')
        ->name('analytics.task-reports.download');
    Route::resource('agencies', AgencyController::class)->except(['show']);
    Route::resource('flights', FlightEntryController::class);
    Route::resource('packages', PackageController::class);
    Route::resource('groups', GroupController::class);
    Route::resource('hotels', HotelController::class);
    Route::resource('hotel-room-types', HotelRoomTypeController::class);
    Route::resource('hotel-rates', HotelRateController::class);
    Route::resource('transport-types', TransportTypeController::class)->except(['show']);
    Route::resource('transport-rates', TransportRateController::class)->except(['show']);
    Route::resource('visa-types', VisaTypeController::class)->except(['show']);
    Route::resource('visa-rates', VisaRateController::class)->except(['show']);
    Route::post('agencies/{agency}/wallet/top-up', [AgencyWalletPaymentController::class, 'storeTopUp'])
        ->middleware('approval.gate:manual_ledger_adjustment,agency')
        ->name('agencies.wallet.top-up');
    Route::get('cms/packages', [CmsCatalogController::class, 'packages'])->name('cms.packages.index');
    Route::get('cms/groups', [CmsCatalogController::class, 'groups'])->name('cms.groups.index');
    Route::resource('seo-pages', SeoPageController::class)->except(['show']);
    Route::resource('content-blocks', ContentBlockController::class)->only(['index', 'edit', 'update']);
    Route::resource('landing-pages', LandingPageController::class)->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('permission:module.marketing.view');
    Route::resource('blog-posts', BlogPostController::class)->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('permission:module.marketing.view');
    Route::resource('promo-codes', PromoCodeController::class)->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('permission:module.marketing.view');
    Route::get('referrals', [ReferralController::class, 'index'])
        ->middleware('permission:module.marketing.view')
        ->name('referrals.index');
    Route::post('referrals', [ReferralController::class, 'store'])
        ->middleware('permission:action.marketing.manage')
        ->name('referrals.store');
    Route::get('packages/{package}/gallery', [PackageGalleryController::class, 'index'])->name('packages.gallery.index');
    Route::post('packages/{package}/gallery', [PackageGalleryController::class, 'store'])->name('packages.gallery.store');
    Route::patch('packages/{package}/gallery/images/{packageImage}', [PackageGalleryController::class, 'update'])->name('packages.gallery.images.update');
    Route::delete('packages/{package}/gallery/images/{packageImage}', [PackageGalleryController::class, 'destroy'])->name('packages.gallery.images.destroy');
    Route::get('groups/{group}/gallery', [GroupGalleryController::class, 'index'])->name('groups.gallery.index');
    Route::post('groups/{group}/gallery', [GroupGalleryController::class, 'store'])->name('groups.gallery.store');
    Route::patch('groups/{group}/gallery/images/{groupImage}', [GroupGalleryController::class, 'update'])->name('groups.gallery.images.update');
    Route::delete('groups/{group}/gallery/images/{groupImage}', [GroupGalleryController::class, 'destroy'])->name('groups.gallery.images.destroy');
    Route::get('inquiries', [InquiryController::class, 'index'])
        ->middleware('permission:module.inquiries.view')
        ->name('inquiries.index');
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])
        ->middleware('permission:module.inquiries.view')
        ->name('inquiries.show');
    Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.update-status');
    Route::post('inquiries/{inquiry}/convert-booking-intent', [InquiryController::class, 'convertToBookingIntent'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.convert-booking-intent');
    Route::post('inquiries/{inquiry}/crm/calls', [InquiryCrmController::class, 'logCall'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.crm.calls');
    Route::post('inquiries/{inquiry}/crm/notes', [InquiryCrmController::class, 'storeNote'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.crm.notes');
    Route::patch('inquiries/{inquiry}/crm/pipeline', [InquiryCrmController::class, 'updatePipeline'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.crm.pipeline');
    Route::post('inquiries/{inquiry}/crm/follow-ups', [InquiryCrmController::class, 'storeFollowUp'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.crm.follow-ups.store');
    Route::patch('inquiries/{inquiry}/crm/follow-ups/{followUp}/complete', [InquiryCrmController::class, 'completeFollowUp'])
        ->middleware('permission:action.inquiries.manage')
        ->name('inquiries.crm.follow-ups.complete');
    Route::get('exports/inquiries.csv', [ExportController::class, 'inquiriesCsv'])->name('exports.inquiries-csv');
    Route::get('exports/bookings.csv', [ExportController::class, 'bookingsCsv'])->name('exports.bookings-csv');
    Route::get('export-history', [ExportHistoryController::class, 'index'])->name('export-history.index');
    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])
        ->middleware('permission:action.quotations.duplicate')
        ->name('quotations.duplicate');
    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('permission:action.quotations.export')
        ->name('quotations.print');
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'exportPdf'])
        ->middleware('permission:action.quotations.export')
        ->name('quotations.pdf');
    Route::post('quotations/{quotation}/bookings', [BookingController::class, 'storeFromQuotation'])
        ->middleware('permission:action.quotations.convert_booking')
        ->name('quotations.bookings.store');
    Route::get('quotations', [QuotationController::class, 'index'])
        ->middleware('permission:module.quotations.view')
        ->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])
        ->middleware('permission:action.quotations.create')
        ->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])
        ->middleware('permission:action.quotations.create')
        ->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:module.quotations.view')
        ->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])
        ->middleware('permission:action.quotations.update')
        ->name('quotations.edit');
    Route::put('quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware('permission:action.quotations.update')
        ->name('quotations.update');
    Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])
        ->middleware('permission:action.quotations.delete')
        ->name('quotations.destroy');

    Route::get('bookings', [BookingController::class, 'index'])
        ->middleware('permission:module.bookings.view')
        ->name('bookings.index');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->middleware('permission:module.bookings.view')
        ->name('bookings.show');
    Route::post('bookings/{booking}/documents', [BookingDocumentController::class, 'store'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.documents.store');
    Route::patch('bookings/{booking}/documents/{document}/validation', [BookingDocumentController::class, 'validateDocument'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.documents.validate');
    Route::get('bookings/{booking}/documents/{document}/download', [BookingDocumentController::class, 'download'])
        ->middleware('permission:module.bookings.view')
        ->name('bookings.documents.download');
    Route::post('bookings/{booking}/documents/{document}/archive', [BookingDocumentController::class, 'archive'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.documents.archive');
    Route::post('bookings/{booking}/documents/{document}/rescan', [BookingDocumentController::class, 'rescan'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.documents.rescan');
    Route::post('bookings/{booking}/hold', [BookingController::class, 'hold'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.hold');
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware('approval.gate:booking_force_cancel,booking')
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.cancel');
    Route::patch('bookings/{booking}/amend', [BookingController::class, 'amend'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.amend');
    Route::post('bookings/{booking}/customer', [BookingController::class, 'assignCustomer'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.assign-customer');
    Route::post('bookings/{booking}/supplier-cost', [BookingController::class, 'updateSupplierCost'])
        ->middleware('permission:action.bookings.manage')
        ->name('bookings.supplier-cost.update');
    Route::get('bookings/{booking}/voucher', [BookingController::class, 'voucher'])
        ->middleware('permission:module.bookings.view')
        ->name('bookings.voucher');
    Route::get('bookings/{booking}/invoice', [BookingController::class, 'invoice'])
        ->middleware('permission:module.bookings.view')
        ->name('bookings.invoice');

    Route::post('bookings/{booking}/payments/deposit', [BookingPaymentController::class, 'storeDeposit'])
        ->middleware('permission:action.bookings.payments')
        ->name('bookings.payments.deposit');
    Route::post('bookings/{booking}/payments/balance', [BookingPaymentController::class, 'storeBalancePayment'])
        ->middleware('permission:action.bookings.payments')
        ->name('bookings.payments.balance');
    Route::post('bookings/{booking}/payments/full', [BookingPaymentController::class, 'storeFullPayment'])
        ->middleware('permission:action.bookings.payments')
        ->name('bookings.payments.full');
    Route::post('bookings/{booking}/payments/wallet', [BookingPaymentController::class, 'storeWalletPayment'])
        ->middleware('permission:action.bookings.payments')
        ->name('bookings.payments.wallet');
    Route::post('payments/{payment}/refund', [PaymentRefundController::class, 'store'])
        ->middleware('approval.gate:payment_refund,payment')
        ->middleware('permission:action.bookings.payments')
        ->name('payments.refund');

    Route::get('support-tickets', [SupportTicketController::class, 'index'])
        ->middleware('permission:module.support.view')
        ->name('support-tickets.index');
    Route::post('support-tickets', [SupportTicketController::class, 'store'])
        ->middleware('permission:action.support.manage')
        ->name('support-tickets.store');
    Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])
        ->middleware('permission:module.support.view')
        ->name('support-tickets.show');
    Route::post('support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply'])
        ->middleware('permission:action.support.manage')
        ->name('support-tickets.reply');
    Route::post('support-tickets/{supportTicket}/resolve', [SupportTicketController::class, 'resolve'])
        ->middleware('permission:action.support.manage')
        ->name('support-tickets.resolve');
    Route::post('support-tickets/{supportTicket}/escalate', [SupportTicketController::class, 'escalate'])
        ->middleware('permission:action.support.manage')
        ->name('support-tickets.escalate');

    Route::get('compliance', [ComplianceController::class, 'dashboard'])
        ->middleware('permission:module.compliance.view')
        ->name('compliance.dashboard');
    Route::post('compliance/approval-requests', [ComplianceController::class, 'storeApproval'])
        ->middleware('permission:action.compliance.manage')
        ->name('compliance.approvals.store');
    Route::post('compliance/approval-requests/{approvalRequest}/review', [ComplianceController::class, 'reviewApproval'])
        ->middleware('permission:action.compliance.manage')
        ->name('compliance.approvals.review');

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('integrations', [IntegrationConnectionController::class, 'index'])->name('integrations.index');
        Route::get('integrations/create', [IntegrationConnectionController::class, 'create'])->name('integrations.create');
        Route::post('integrations', [IntegrationConnectionController::class, 'store'])->name('integrations.store');
        Route::get('integrations/{integration}', [IntegrationConnectionController::class, 'show'])->whereNumber('integration')->name('integrations.show');
        Route::get('integrations/{integration}/edit', [IntegrationConnectionController::class, 'edit'])->whereNumber('integration')->name('integrations.edit');
        Route::put('integrations/{integration}', [IntegrationConnectionController::class, 'update'])->whereNumber('integration')->name('integrations.update');
        Route::delete('integrations/{integration}', [IntegrationConnectionController::class, 'destroy'])->whereNumber('integration')->name('integrations.destroy');
        Route::post('integrations/{integration}/test', IntegrationConnectionTestController::class)->whereNumber('integration')->name('integrations.test');
        Route::post('integrations/{integration}/test-connection', IntegrationConnectionTestController::class)
            ->whereNumber('integration')
            ->name('integrations.test-connection');
        Route::post('integrations/{integration}/toggle', [IntegrationConnectionController::class, 'toggleActive'])
            ->whereNumber('integration')
            ->middleware('approval.gate:provider_production_activation,integration')
            ->name('integrations.toggle');
        Route::post('integrations/{integration}/default', [IntegrationConnectionController::class, 'setDefault'])->whereNumber('integration')->name('integrations.default');
        Route::get('integrations/access-matrix', [TenantProviderAccessController::class, 'index'])->name('integrations.access-matrix.index');
        Route::put('integrations/access-matrix/{tenant}', [TenantProviderAccessController::class, 'update'])->name('integrations.access-matrix.update');
        Route::get('system/modules/access-matrix', [TenantProviderAccessController::class, 'index'])->name('modules.access-matrix.index');
        Route::get('system/modules/tenant-access', function (): RedirectResponse {
            return redirect()->route('admin.integrations.access-matrix.index');
        })->name('modules.tenant-access.index');
        Route::get('system/modules/plan-restrictions', function (): RedirectResponse {
            return redirect()->route('admin.tenants.plans.index');
        })->name('modules.plan-restrictions.index');

        Route::get('integrations/providers', [IntegrationProviderController::class, 'index'])->name('integrations.providers.index');
        Route::get('integrations/search-results', [FlightSearchResultController::class, 'index'])->name('integrations.search-results.index');
        Route::get('integrations/search-results/{searchSession}', [FlightSearchResultController::class, 'show'])->name('integrations.search-results.show');
        Route::get('integrations/accounts', [IntegrationSupplierAccountController::class, 'index'])->name('integrations.accounts.index');
        Route::get('integrations/accounts/create', [IntegrationSupplierAccountController::class, 'create'])->name('integrations.accounts.create');
        Route::post('integrations/accounts', [IntegrationSupplierAccountController::class, 'store'])->name('integrations.accounts.store');
        Route::get('integrations/accounts/connections/{connection}', [IntegrationSupplierAccountController::class, 'show'])->name('integrations.accounts.show');
        Route::get('integrations/accounts/{accountKey}/edit', [IntegrationSupplierAccountController::class, 'edit'])->name('integrations.accounts.edit');
        Route::put('integrations/accounts/{accountKey}', [IntegrationSupplierAccountController::class, 'update'])->name('integrations.accounts.update');
        Route::delete('integrations/accounts/{accountKey}', [IntegrationSupplierAccountController::class, 'destroy'])->name('integrations.accounts.destroy');
        Route::post('integrations/accounts/default', [IntegrationSupplierAccountController::class, 'setDefault'])->name('integrations.accounts.default');
        Route::post('integrations/accounts/test-connection', [IntegrationSupplierAccountController::class, 'testConnection'])->name('integrations.accounts.test-connection');
        Route::get('integrations/policies', [TenantIntegrationPolicyController::class, 'index'])->name('integrations.policies.index');
        Route::get('integrations/policies/{tenant}/edit', [TenantIntegrationPolicyController::class, 'edit'])->name('integrations.policies.edit');
        Route::put('integrations/policies/{tenant}', [TenantIntegrationPolicyController::class, 'update'])->name('integrations.policies.update');
        Route::get('tenants/governance', [TenantPlanController::class, 'index'])->name('tenants.plans.index');
        Route::get('tenants/{tenant}/plan', [TenantPlanController::class, 'edit'])->name('tenants.plans.edit');
        Route::put('tenants/{tenant}/plan', [TenantPlanController::class, 'update'])
            ->middleware('approval.gate:tenant_plan_change,tenant')
            ->name('tenants.plans.update');
        Route::get('tenants/{tenant}/modules', [TenantModuleAccessController::class, 'edit'])->name('tenants.modules.edit');
        Route::put('tenants/{tenant}/modules', [TenantModuleAccessController::class, 'update'])->name('tenants.modules.update');
        Route::get('tenants/{tenant}/providers', [TenantModuleAccessController::class, 'editProviders'])->name('tenants.providers.edit');
        Route::put('tenants/{tenant}/providers', [TenantModuleAccessController::class, 'updateProviders'])->name('tenants.providers.update');

        Route::get('system/tenancy', [TenancySettingsController::class, 'edit'])->name('system.tenancy.edit');
        Route::put('system/tenancy', [TenancySettingsController::class, 'update'])
            ->middleware('approval.gate:tenancy_toggle')
            ->name('system.tenancy.update');
        Route::get('system/permissions', [PermissionMatrixController::class, 'index'])->name('system.permissions.index');
        Route::get('system/permissions/{role}', [PermissionMatrixController::class, 'edit'])->name('system.permissions.edit');
        Route::put('system/permissions/{role}', [PermissionMatrixController::class, 'update'])->name('system.permissions.update');
        Route::get('system/permissions-export', [PermissionMatrixController::class, 'export'])->name('system.permissions.export');
        Route::get('system/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('system/settings/{section}', [SettingsController::class, 'section'])->name('settings.section');
        Route::put('system/settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('system/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::put('system/modules/{module}', [ModuleCatalogController::class, 'update'])->name('modules.update');
        Route::get('system/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::put('system/modules/{module}/configuration', [ModuleController::class, 'updateConfiguration'])->name('modules.configuration.update');
        Route::post('system/modules/{module}/test-connection', [ModuleController::class, 'testConnection'])->name('modules.test-connection');

        Route::get('system/module-management', [ModuleController::class, 'index'])->name('module-management.index');
        Route::put('system/modules/{module}/status', [ModuleController::class, 'updateStatus'])
            ->middleware('approval.gate:provider_production_activation,module')
            ->name('modules.status.update');
        Route::put('system/modules/{module}/credentials', [ModuleCredentialController::class, 'update'])
            ->middleware('approval.gate:provider_production_credential_replace,module')
            ->name('modules.credentials.update');
        Route::post('system/modules/{module}/health-check', [ModuleHealthCheckController::class, 'store'])->name('modules.health-check.store');
        Route::put('system/modules/{module}/pricing', [ModulePricingController::class, 'update'])->name('modules.pricing.update');
        Route::put('system/modules/{module}/tax', [ModuleTaxController::class, 'update'])->name('modules.tax.update');

        Route::put('system/settings/system', [SystemSettingsController::class, 'update'])->name('system-settings.update');
        Route::put('system/settings/branding', [BrandingSettingsController::class, 'update'])->name('branding-settings.update');
        Route::put('system/settings/notifications', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
        Route::put('system/settings/features', [FeatureFlagController::class, 'update'])->name('feature-flags.update');
        Route::get('cms/control-panel', function (): RedirectResponse {
            return redirect()->route('admin.cms.settings.edit');
        })->name('cms.control-panel');
        Route::get('cms/settings', [CmsSettingsController::class, 'edit'])->name('cms.settings.edit');
        Route::put('cms/settings', [CmsSettingsController::class, 'update'])->name('cms.settings.update');
        Route::get('finance/control-panel', function (): RedirectResponse {
            return redirect()->route('admin.finance.settings.edit');
        })->name('finance.control-panel');
        Route::get('finance/settings', [FinanceSettingsController::class, 'edit'])->name('finance.settings.edit');
        Route::put('finance/settings', [FinanceSettingsController::class, 'update'])
            ->middleware('approval.gate:dangerous_setting_change')
            ->name('finance.settings.update');
        Route::get('documents/control-panel', function (): RedirectResponse {
            return redirect()->route('admin.documents.security-settings.edit');
        })->name('documents.control-panel');
        Route::get('documents/security-settings', [DocumentSecuritySettingsController::class, 'edit'])->name('documents.security-settings.edit');
        Route::put('documents/security-settings', [DocumentSecuritySettingsController::class, 'update'])
            ->middleware('approval.gate:document_security_override')
            ->name('documents.security-settings.update');
        Route::get('system/airports', [AirportDirectoryController::class, 'edit'])->name('system.airports.edit');
        Route::put('system/airports', [AirportDirectoryController::class, 'update'])->name('system.airports.update');
        Route::get('monitoring', function (): RedirectResponse {
            return redirect()->route('admin.monitoring.health-overview');
        })->name('monitoring.index');
        Route::get('monitoring/health-overview', [HealthMonitoringController::class, 'overview'])->name('monitoring.health-overview');
        Route::get('monitoring/integration-logs', [HealthMonitoringController::class, 'integrationLogs'])->name('monitoring.integration-logs');
        Route::get('monitoring/async-task-monitor', [HealthMonitoringController::class, 'asyncTasks'])->name('monitoring.async-task-monitor');
        Route::get('monitoring/failed-jobs-alerts', [HealthMonitoringController::class, 'failedAlerts'])->name('monitoring.failed-jobs-alerts');
    });
});
