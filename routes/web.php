<?php

use App\Http\Controllers\PrintController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

/** Switch the panel language (ar / en) and remember it for the signed-in user. */
Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);
    session(['locale' => $locale]);
    auth()->user()?->update(['locale' => $locale]);

    return back();
})->middleware('web')->name('locale.switch');

/** Signed, time-limited print links used by the mobile app (no session needed). */
Route::get('/print/s/{type}/{id}', [PrintController::class, 'document'])
    ->middleware('signed')->name('print.signed');

Route::middleware(['web', 'auth'])->prefix('print')->name('print.')->group(function () {
    Route::get('/barcodes', [PrintController::class, 'barcodes'])->name('barcodes');
    Route::get('/{type}/{id}', [PrintController::class, 'document'])->name('document');
});
