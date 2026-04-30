<?php

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\AirportDirectoryController;
use App\Http\Controllers\Frontend\GroupController;
use App\Http\Controllers\Frontend\InquiryController;
use App\Http\Controllers\Frontend\LegacyRouteController;
use App\Http\Controllers\Frontend\LandingPageController;
use App\Http\Controllers\Frontend\PackageController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\DuffelConnectivityTestController;
use App\Http\Controllers\Frontend\FlightBookingController;
use App\Http\Controllers\Frontend\FlightSearchController;
use App\Http\Controllers\Frontend\PromoCodeController;
use App\Http\Controllers\Frontend\ReferralController;
use Illuminate\Support\Facades\Route;

Route::middleware(['frontend.seo'])->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('frontend.home');
    Route::get('/about', [PageController::class, 'about'])->name('frontend.about');
    Route::get('/contact', [PageController::class, 'contact'])->name('frontend.contact');
    Route::get('/bank-details', [PageController::class, 'bank'])->name('frontend.bank');
    Route::get('/packages', [PackageController::class, 'index'])->name('frontend.packages.index');
    Route::get('/packages/{slug}', [PackageController::class, 'show'])->name('frontend.packages.show');
    Route::get('/groups', [GroupController::class, 'index'])->name('frontend.groups.index');
    Route::get('/groups/{slug}', [GroupController::class, 'show'])->name('frontend.groups.show');
    Route::get('/flights/search', [FlightSearchController::class, 'search'])->name('frontend.flights.search');
    Route::get('/flights/results', [FlightSearchController::class, 'results'])->name('frontend.flights.results');
    Route::post('/flights/proceed', [FlightBookingController::class, 'proceed'])->name('frontend.flights.proceed');
    Route::get('/flights/booking/review', [FlightBookingController::class, 'showBookingReview'])->name('frontend.flights.booking.review');
    Route::post('/flights/booking/continue', [FlightBookingController::class, 'continueToBooking'])->name('frontend.flights.booking.continue');
    Route::get('/flights/booking', [FlightBookingController::class, 'showBookingForm'])->name('frontend.flights.booking.show');
    Route::post('/flights/book', [FlightBookingController::class, 'book'])->name('frontend.flights.book');
    Route::get('/quote-inquiry', [InquiryController::class, 'createQuote'])->name('frontend.inquiries.quote');
    Route::post('/quote-inquiry', [InquiryController::class, 'storeQuote'])->name('frontend.inquiries.store-quote');
    Route::post('/inquiries/package', [InquiryController::class, 'storePackage'])->name('frontend.inquiries.store-package');
    Route::post('/inquiries/group', [InquiryController::class, 'storeGroup'])->name('frontend.inquiries.store-group');
    Route::get('/blog', [BlogController::class, 'index'])->name('frontend.blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('frontend.blog.show');
    Route::get('/landing/{slug}', [LandingPageController::class, 'show'])->name('frontend.landing.show');
    Route::post('/promotions/validate', [PromoCodeController::class, 'validateCode'])->name('frontend.promotions.validate');
    Route::post('/referrals/convert', [ReferralController::class, 'convert'])->name('frontend.referrals.convert');
    Route::get('/airports/search', [AirportDirectoryController::class, 'search'])->name('frontend.airports.search');
    Route::get('/airports/index/global', [AirportDirectoryController::class, 'globalIndex'])->name('frontend.airports.global-index');
    Route::get('/test-duffel', DuffelConnectivityTestController::class)->name('frontend.debug.duffel-connectivity');
});

Route::prefix('raw')->group(function (): void {
    Route::get('/package/index.php', [LegacyRouteController::class, 'packageIndex'])->name('frontend.legacy.package-index');
    Route::get('/groups-by-filter-new.php', [LegacyRouteController::class, 'groupsByFilter'])->name('frontend.legacy.groups-by-filter');
});
