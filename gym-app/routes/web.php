<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('/print/card/{member}', [PrintController::class, 'card'])->name('print.card');
    Route::get('/print/receipt/{payment}', [PrintController::class, 'receipt'])->name('print.receipt');
    Route::get('/print/sale/{sale}', [PrintController::class, 'sale'])->name('print.sale');
    Route::get('/export/members', [PrintController::class, 'exportMembers'])->name('export.members');
});
