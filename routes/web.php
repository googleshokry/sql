<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware(['web', 'auth'])->prefix('print')->name('print.')->group(function () {
    Route::get('/barcodes', [PrintController::class, 'barcodes'])->name('barcodes');
    Route::get('/{type}/{id}', [PrintController::class, 'document'])->name('document');
});
