<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing', [
    'plans' => App\Models\SaasPlan::where('is_active', true)->orderBy('sort')->get(),
]))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/print/card/{member}', [PrintController::class, 'card'])->name('print.card');
    Route::get('/print/receipt/{payment}', [PrintController::class, 'receipt'])->name('print.receipt');
    Route::get('/print/sale/{sale}', [PrintController::class, 'sale'])->name('print.sale');
    Route::get('/export/{gym:slug}/members', [PrintController::class, 'exportMembers'])->name('export.members');
});

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, App\Http\Middleware\SetLocale::SUPPORTED, true), 404);
    session(['locale' => $locale]);
    auth()->user()?->update(['locale' => $locale]);

    return redirect()->back();
})->name('locale');
