<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Tenancy\TenantManager::class);
        $this->app->bind(\Filament\Auth\Http\Responses\Contracts\LoginResponse::class, \App\Http\Responses\PanelLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // اللغة الافتراضية الأصلية (app()->setLocale بيغير app.locale نفسه)
        config(['app.default_locale' => config('app.locale')]);
        // إعدادات .env الافتراضية للمطاعم (config('pos.*') بيتغير لكل مطعم)
        config(['pos_defaults' => config('pos')]);

        // عند تسجيل الدخول في مطعم نحفظ المطعم في الجلسة (عشان نفس الجلسة ماتفتحش مطعم تاني)
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            if ($event->guard === 'web' && tenant() && request()->hasSession()) {
                request()->session()->put('auth_tenant', tenant()->slug);
                if ($event->user->locale) {
                    request()->session()->put('locale', $event->user->locale);
                }
            }
        });
    }
}
