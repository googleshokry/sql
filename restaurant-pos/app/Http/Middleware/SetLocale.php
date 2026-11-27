<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * اللغة: الـ API من هيدر Accept-Language، والويب من اختيار المستخدم (session ثم حسابه).
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('api/*')
            ? $request->getPreferredLanguage(self::SUPPORTED)
            : ($request->query('lang')
                ?? ($request->hasSession() ? $request->session()->get('locale') : null)
                ?? $request->user()?->locale);

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
