<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الاشتراك: موقوف = ممنوع. منتهي = المدير يقدر يدخل صفحة الاشتراك بس عشان يجدد.
 */
class EnsureTenantCanOperate
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        if (! $tenant || $tenant->canOperate()) {
            return $next($request);
        }

        $state = $tenant->accessState();
        $message = $state === 'suspended'
            ? __('حساب المطعم موقوف. تواصل مع إدارة المنصة.')
            : __('انتهى اشتراك المطعم. جدد الاشتراك عشان تكمل.');

        if ($request->is('api/*')) {
            return response()->json(['message' => $message, 'subscription' => $state], 402);
        }

        $user = $request->user();
        $allowed = $request->routeIs('filament.app.pages.billing', 'filament.app.auth.logout', 'billing.*');
        if ($state === 'expired' && $user?->isAdmin()) {
            if ($allowed) {
                return $next($request);
            }

            return redirect()->route('filament.app.pages.billing');
        }

        if ($request->routeIs('filament.app.auth.logout')) {
            return $next($request);
        }

        return response()->view('tenancy.blocked', ['tenant' => $tenant, 'message' => $message], 402);
    }
}
