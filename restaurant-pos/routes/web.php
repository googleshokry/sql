<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// تغيير اللغة (عربي / English)
Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED, true), 404);
    session(['locale' => $locale]);
    auth()->user()?->update(['locale' => $locale]);

    return redirect()->back();
})->name('locale.switch');

// رابط موقّع للفاتورة يفتح من تطبيقات الموبايل بدون تسجيل دخول
Route::get('/r/{order}', [ReceiptController::class, 'customer'])->middleware('signed')->name('orders.receipt.signed');

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/receipt', [ReceiptController::class, 'customer'])->name('orders.receipt');
    Route::get('/orders/{order}/kitchen', [ReceiptController::class, 'kitchen'])->name('orders.kitchen');
});
