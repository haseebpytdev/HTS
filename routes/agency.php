<?php

use App\Http\Controllers\Agency\DashboardController;
use App\Http\Controllers\Agency\InquiryController;
use App\Http\Controllers\Agency\ProfileController;
use App\Http\Controllers\Agency\QuotationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:agency_user'])
    ->prefix('agency')
    ->name('agency.')
    ->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:module.agency.dashboard.view')
        ->name('dashboard');
    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('permission:module.agency.quotations.view')
        ->name('quotations.index');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:module.agency.quotations.view')
        ->name('quotations.show');
    Route::post('/quotations/{quotation}/revision', [QuotationController::class, 'requestRevision'])
        ->middleware('permission:action.agency.quotations.revision')
        ->name('quotations.revision');
    Route::post('/quotations/{quotation}/booking-intent', [QuotationController::class, 'bookingIntent'])
        ->middleware('permission:action.agency.quotations.booking_intent')
        ->name('quotations.booking-intent');
    Route::get('/inquiries', [InquiryController::class, 'index'])
        ->middleware('permission:module.agency.inquiries.view')
        ->name('inquiries.index');
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('permission:module.agency.profile.view')
        ->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('permission:action.agency.profile.update')
        ->name('profile.update');
});
