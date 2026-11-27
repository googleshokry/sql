<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * اللغة: الـ API من هيدر Accept-Language، والويب من الجلسة
 * (لغة المستخدم المحفوظة بتتنقل للجلسة عند تسجيل الدخول).
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('api/*')
            ? $request->getPreferredLanguage(self::SUPPORTED)
            : ($request->query('lang') ?? ($request->hasSession() ? $request->session()->get('locale') : null));

        // لو مفيش اختيار نرجع للافتراضي (ما نعتمدش على حالة طلب سابق)
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : config('app.default_locale', 'ar');
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
