<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverTrackingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

// كل الـ API جوه مطعم: /api/{tenant}/...  (tenant = كود المطعم)
Route::prefix('{tenant}')->middleware(['tenant', 'tenant.active'])->group(function () {
    // ---------- عام ----------
    Route::get('settings', [PublicController::class, 'settings']);
    Route::get('menu', [PublicController::class, 'menu']);
    Route::get('delivery-areas', [PublicController::class, 'deliveryAreas']);

    // ---------- الدخول ----------
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('staff/login', [AuthController::class, 'staffLogin']);
        Route::post('customer/login', [AuthController::class, 'customerLogin'])->middleware('plan.feature:customer_app');
        Route::post('customer/register', [AuthController::class, 'customerRegister'])->middleware('plan.feature:customer_app');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // ---------- الكاشير والمدير ----------
        Route::middleware('actor:admin,cashier')->group(function () {
            Route::get('pos/tables', [PosController::class, 'tables']);
            Route::get('pos/drivers', [PosController::class, 'drivers']);
            Route::get('pos/customers/lookup', [PosController::class, 'lookupCustomer']);
            Route::post('pos/orders', [PosController::class, 'store']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show']);
            Route::post('orders/{order}/pay', [OrderController::class, 'pay']);
            Route::post('orders/{order}/status', [OrderController::class, 'status']);
            Route::post('orders/{order}/driver', [OrderController::class, 'assignDriver']);

            Route::get('drivers/locations', DriverTrackingController::class);
            Route::get('drivers/{driver}/trail', [DriverTrackingController::class, 'trail']);
        });

        // ---------- المالك / المدير ----------
        Route::get('dashboard', DashboardController::class)->middleware('actor:admin');

        // ---------- الطيار ----------
        Route::middleware('actor:driver')->prefix('driver')->group(function () {
            Route::get('orders', [DriverController::class, 'orders']);
            Route::post('shift', [DriverController::class, 'shift']);
            Route::post('location', [DriverController::class, 'location'])->middleware('throttle:120,1');
            Route::post('orders/{order}/pickup', [DriverController::class, 'pickup']);
            Route::post('orders/{order}/arrived', [DriverController::class, 'arrived']);
            Route::post('orders/{order}/delivered', [DriverController::class, 'delivered']);
            Route::post('orders/{order}/failed', [DriverController::class, 'failed']);
        });

        // ---------- العميل ----------
        Route::middleware(['actor:customer', 'plan.feature:customer_app'])->prefix('customer')->group(function () {
            Route::put('profile', [CustomerController::class, 'updateProfile']);
            Route::get('orders', [CustomerController::class, 'orders']);
            Route::post('orders', [CustomerController::class, 'store']);
            Route::get('orders/{id}', [CustomerController::class, 'show']);
            Route::post('orders/{id}/cancel', [CustomerController::class, 'cancel']);
        });
    });
});
