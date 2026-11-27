<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/receipt', [ReceiptController::class, 'customer'])->name('orders.receipt');
    Route::get('/orders/{order}/kitchen', [ReceiptController::class, 'kitchen'])->name('orders.kitchen');
});
