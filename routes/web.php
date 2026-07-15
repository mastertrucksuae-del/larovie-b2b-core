<?php

use App\Http\Controllers\BusinessAccountController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public wholesale catalogue (guest, no auth)
Route::get('/', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/product/{product:handle}', [CatalogueController::class, 'show'])->name('catalogue.show');

Route::get('/cart', [InquiryController::class, 'cart'])->name('cart');
Route::post('/inquiry', [InquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('inquiry.store');
Route::get('/inquiry/{reference}/confirmation', [InquiryController::class, 'confirmation'])
    ->name('inquiry.confirmation');

// Trust & contact (P0 #2, #3, #4)
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSend'])
    ->middleware('throttle:10,1')
    ->name('contact.send');
Route::get('/authenticity', [PageController::class, 'authenticity'])->name('authenticity');

// Business account (KYC) registration + session (P1 #10)
Route::get('/register', [BusinessAccountController::class, 'create'])->name('register');
Route::post('/register', [BusinessAccountController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('register.store');
Route::get('/login', [BusinessAccountController::class, 'showLogin'])->name('login');
Route::post('/login', [BusinessAccountController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');
Route::post('/logout', [BusinessAccountController::class, 'logout'])->name('logout');
Route::get('/account', [BusinessAccountController::class, 'dashboard'])
    ->middleware('auth:business')
    ->name('account');

// Admin-only KYC document download (default web guard = logged-in admin).
Route::get('/admin/business-accounts/{account}/licence', [BusinessAccountController::class, 'licence'])
    ->middleware('auth')
    ->name('business-account.licence');

// SEO (P1 #9)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Language switch
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Signed quote PDF (used by the WhatsApp link). No auth — protected by signature + expiry.
Route::get('/quote/{inquiry}/download', [QuoteController::class, 'download'])
    ->name('quote.download')
    ->middleware('signed');
