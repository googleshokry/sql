<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

/*
| Mobile app API (v1). Send "Accept-Language: ar|en" to get messages in that language,
| and "Authorization: Bearer <token>" for authenticated endpoints.
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::get('gym', [PublicController::class, 'gym']);
    Route::get('plans', [PublicController::class, 'plans']);
    Route::get('announcements', [PublicController::class, 'announcements']);

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/member/login', [AuthController::class, 'memberLogin']);
        Route::post('auth/staff/login', [AuthController::class, 'staffLogin']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::middleware('abilities:member')->prefix('member')->group(function () {
            Route::get('me', [MemberController::class, 'me']);
            Route::get('home', [MemberController::class, 'home']);
            Route::put('profile', [MemberController::class, 'updateProfile']);
            Route::post('password', [MemberController::class, 'changePassword']);
            Route::get('subscriptions', [MemberController::class, 'subscriptions']);
            Route::get('attendances', [MemberController::class, 'attendances']);
            Route::get('payments', [MemberController::class, 'payments']);
            Route::get('measurements', [MemberController::class, 'measurements']);
        });

        Route::middleware('abilities:staff')->prefix('staff')->group(function () {
            Route::get('dashboard', [StaffController::class, 'dashboard']);
            Route::post('checkin', [StaffController::class, 'checkIn'])->middleware('throttle:120,1');
            Route::get('members', [StaffController::class, 'members']);
            Route::get('members/{member}', [StaffController::class, 'member']);
            Route::get('inside', [StaffController::class, 'inside']);
            Route::post('attendances/{attendance}/checkout', [StaffController::class, 'checkout']);
        });
    });
});
