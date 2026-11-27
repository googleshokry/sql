<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// رابط موقّع للفاتورة يفتح من تطبيقات الموبايل بدون تسجيل دخول
Route::get('/r/{order}', [ReceiptController::class, 'customer'])->middleware('signed')->name('orders.receipt.signed');

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/receipt', [ReceiptController::class, 'customer'])->name('orders.receipt');
    Route::get('/orders/{order}/kitchen', [ReceiptController::class, 'kitchen'])->name('orders.kitchen');
});
