<?php

use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\Integrations\BookingController as IntegrationsBookingController;
use App\Http\Controllers\Api\V1\Integrations\FlightPricingController;
use App\Http\Controllers\Api\V1\Integrations\FlightSearchController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\TransportRateController;
use App\Http\Controllers\Api\V1\VisaRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('health');

    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/hotels', [HotelController::class, 'index'])->name('hotels.index');
    Route::get('/visa-rates', [VisaRateController::class, 'index'])->name('visa-rates.index');
    Route::get('/transport-rates', [TransportRateController::class, 'index'])->name('transport-rates.index');

    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');

    Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');

    Route::prefix('integrations')
        ->name('integrations.')
        ->middleware(['integration.auth', 'throttle:integrations-api', 'integration.idempotency', 'compliance.audit'])
        ->group(function (): void {
        Route::post('/flight-search', [FlightSearchController::class, 'store'])->name('flight-search.store');
        Route::post('/flight-pricing', [FlightPricingController::class, 'store'])->name('flight-pricing.store');
        Route::post('/booking', [IntegrationsBookingController::class, 'store'])->name('booking.store');
    });
});
