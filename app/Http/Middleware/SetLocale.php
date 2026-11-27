<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the interface language (ar / en) for both the web panel and the mobile API.
 *
 * Web order: ?lang= query → session → signed-in user's preference → Accept-Language header → app default.
 * API order: ?lang= query → Accept-Language header (sent by the mobile app) → user's preference → app default.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        if ($request->is('api/*')) {
            $header = $request->hasHeader('Accept-Language') ? $request->getPreferredLanguage(self::SUPPORTED) : null;

            return $this->firstSupported([$request->query('lang'), $header, $request->user('sanctum')?->locale]);
        }

        return $this->firstSupported([
            $request->query('lang'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
            $request->getPreferredLanguage(self::SUPPORTED),
        ]);
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    protected function firstSupported(array $candidates): string
    {
        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
                return $locale;
            }
        }

        return config('app.locale');
    }
}
