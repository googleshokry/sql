<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymobController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SignupController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// ================= المنصة (مركزي) =================
Route::get('/', LandingController::class)->name('landing');
Route::get('/register', [SignupController::class, 'create'])->name('signup');
Route::post('/register', [SignupController::class, 'store'])->middleware('throttle:5,1')->name('signup.store');
Route::get('/find', [SignupController::class, 'find'])->name('tenant.find');

// Paymob: إشعار الدفع (server-to-server) + رجوع العميل بعد الدفع
Route::post('/paymob/webhook', [PaymobController::class, 'webhook'])->name('paymob.webhook');
Route::get('/paymob/return', [PaymobController::class, 'return'])->name('paymob.return');

// تغيير اللغة خارج أي مطعم (الصفحة الرئيسية ولوحة المنصة)
Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);
    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.central');

// ================= جوه المطعم: /app/{tenant}/... =================
Route::prefix('app/{tenant}')->middleware('tenant')->group(function () {
    // تغيير اللغة وحفظها في حساب المستخدم
    Route::get('/locale/{locale}', function (string $locale) {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);
        session(['locale' => $locale]);
        auth()->user()?->update(['locale' => $locale]);

        return redirect()->back();
    })->name('locale.switch');

    // رابط موقّع للفاتورة يفتح من تطبيقات الموبايل بدون تسجيل دخول
    Route::get('/r/{order}', [ReceiptController::class, 'customer'])->middleware('signed')->name('orders.receipt.signed');

    Route::middleware(['auth', 'tenant.active'])->group(function () {
        Route::get('/orders/{order}/receipt', [ReceiptController::class, 'customer'])->name('orders.receipt');
        Route::get('/orders/{order}/kitchen', [ReceiptController::class, 'kitchen'])->name('orders.kitchen');
        Route::post('/billing/paymob', [PaymobController::class, 'checkout'])->name('billing.paymob');
    });
});
