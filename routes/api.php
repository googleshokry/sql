<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SalesInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('auth/locale', [AuthController::class, 'updateLocale'])->name('auth.locale');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('lookups', LookupController::class)->name('lookups');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/barcode/{code}', [ProductController::class, 'byBarcode'])->name('products.barcode');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');

        Route::get('sales-invoices', [SalesInvoiceController::class, 'index'])->name('sales-invoices.index');
        Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])->name('sales-invoices.store');
        Route::get('sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show'])->name('sales-invoices.show');
        Route::post('sales-invoices/{salesInvoice}/payments', [SalesInvoiceController::class, 'addPayment'])->name('sales-invoices.payments');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });
});
