<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
        $middleware->api(append: [\App\Http\Middleware\SetLocale::class]);
        $middleware->redirectGuestsTo(fn () => tenant() ? route('filament.app.auth.login') : url('/'));
        // تحديد المطعم (وقاعدة بياناته) لازم يحصل قبل تسجيل الدخول
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\InitializeTenancy::class,
        );
        // Paymob بيبعت الإشعار من سيرفره (من غير CSRF token) — محمي بـ HMAC
        $middleware->validateCsrfTokens(except: ['paymob/webhook']);
        $middleware->alias([
            'actor' => \App\Http\Middleware\EnsureActor::class,
            'tenant' => \App\Http\Middleware\InitializeTenancy::class,
            'plan.feature' => \App\Http\Middleware\EnsurePlanFeature::class,
            'tenant.active' => \App\Http\Middleware\EnsureTenantCanOperate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
