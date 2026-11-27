<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NurseryContextController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // --- Auth (phone + OTP) ---
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Not nursery-scoped: who am I, which nurseries, which role in each.
        Route::get('me', MeController::class);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{token}', [DeviceController::class, 'destroy']);

        // Everything below requires X-Nursery-Id and membership in that nursery.
        Route::middleware('nursery')->group(function () {
            Route::get('nursery', NurseryContextController::class);
            // Phase 2+: children, attendance/scan, reports, media ...
        });
    });
});
