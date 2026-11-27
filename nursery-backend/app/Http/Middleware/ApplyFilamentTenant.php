<?php

namespace App\Http\Middleware;

use App\Support\CurrentNursery;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered as tenantMiddleware on the nursery panel (persistent, so Livewire requests get it too).
 * Copies Filament's tenant into CurrentNursery so the BelongsToNursery scope and spatie teams follow it.
 */
class ApplyFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        app(CurrentNursery::class)->set($tenant);

        if ($tenant && ! $tenant->isActive()) {
            abort(403, __('app.nursery_inactive'));
        }

        if ($user = $request->user()) {
            app()->setLocale($user->locale ?: config('app.locale'));
        }

        return $next($request);
    }
}
