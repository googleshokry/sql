<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks ar/en from Accept-Language (mobile app), else the user's saved locale, else the app default.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        if (! $request->hasHeader('Accept-Language') && $request->user()?->locale) {
            $locale = $request->user()->locale;
        }

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
