<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the UI language (ar / en): explicit ?lang, then the session, then the logged-in panel user,
 * then the Accept-Language header (sent by the mobile app), then the app default.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');

        if (! $this->valid($locale) && $request->hasSession()) {
            $locale = $request->session()->get('locale');
        }
        if (! $this->valid($locale) && ! $request->is('api/*')) {
            $locale = $request->user()?->locale;
        }
        if (! $this->valid($locale) && $request->header('Accept-Language')) {
            $locale = substr($request->getPreferredLanguage(self::SUPPORTED) ?? '', 0, 2);
            if (! str_starts_with((string) $request->header('Accept-Language'), $locale)) {
                $locale = null; // header did not actually ask for a supported language
            }
        }
        if (! $this->valid($locale)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }

    private function valid(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::SUPPORTED, true);
    }
}
