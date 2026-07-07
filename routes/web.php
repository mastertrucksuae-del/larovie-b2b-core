<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QuoteController;
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

// Language switch
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Signed quote PDF (used by the WhatsApp link). No auth — protected by signature + expiry.
Route::get('/quote/{inquiry}/download', [QuoteController::class, 'download'])
    ->name('quote.download')
    ->middleware('signed');
