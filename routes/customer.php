<?php

use App\Http\Controllers\Customer\CustomerAuthenticatedSessionController;
use App\Http\Controllers\Customer\CustomerBookingController;
use App\Http\Controllers\Customer\CustomerBookingDocumentController;
use App\Http\Controllers\Customer\CustomerBookingFileController;
use App\Http\Controllers\Customer\CustomerBookingPaymentController;
use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Http\Controllers\Customer\CustomerNewPasswordController;
use App\Http\Controllers\Customer\CustomerPasswordResetLinkController;
use App\Http\Controllers\Customer\CustomerRegisteredUserController;
use App\Http\Controllers\Customer\CustomerSavedTravelerController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->name('customer.')->group(function (): void {
    Route::middleware('customer.guest')->group(function (): void {
        Route::get('register', [CustomerRegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [CustomerRegisteredUserController::class, 'store']);

        Route::get('login', [CustomerAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [CustomerAuthenticatedSessionController::class, 'store']);

        Route::get('forgot-password', [CustomerPasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [CustomerPasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('reset-password/{token}', [CustomerNewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [CustomerNewPasswordController::class, 'store'])->name('password.store');
    });

    Route::middleware(['auth:customer', 'session.max_age'])->group(function (): void {
        Route::post('logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('dashboard', [CustomerDashboardController::class, 'show'])->name('dashboard');

        Route::get('bookings', [CustomerBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [CustomerBookingController::class, 'show'])->name('bookings.show');

        Route::post('bookings/{booking}/payments/deposit', [CustomerBookingPaymentController::class, 'storeDeposit'])->name('bookings.payments.deposit');
        Route::post('bookings/{booking}/payments/balance', [CustomerBookingPaymentController::class, 'storeBalancePayment'])->name('bookings.payments.balance');
        Route::post('bookings/{booking}/payments/full', [CustomerBookingPaymentController::class, 'storeFullPayment'])->name('bookings.payments.full');

        Route::get('bookings/{booking}/voucher', [CustomerBookingDocumentController::class, 'voucher'])->name('bookings.voucher');
        Route::get('bookings/{booking}/invoice', [CustomerBookingDocumentController::class, 'invoice'])->name('bookings.invoice');
        Route::get('bookings/{booking}/documents/{document}/download', [CustomerBookingFileController::class, 'download'])->name('bookings.documents.download');

        Route::get('saved-travelers', [CustomerSavedTravelerController::class, 'index'])->name('saved-travelers.index');
        Route::get('saved-travelers/create', [CustomerSavedTravelerController::class, 'create'])->name('saved-travelers.create');
        Route::post('saved-travelers', [CustomerSavedTravelerController::class, 'store'])->name('saved-travelers.store');
        Route::get('saved-travelers/{saved_traveler}/edit', [CustomerSavedTravelerController::class, 'edit'])->name('saved-travelers.edit');
        Route::put('saved-travelers/{saved_traveler}', [CustomerSavedTravelerController::class, 'update'])->name('saved-travelers.update');
        Route::delete('saved-travelers/{saved_traveler}', [CustomerSavedTravelerController::class, 'destroy'])->name('saved-travelers.destroy');
    });
});
