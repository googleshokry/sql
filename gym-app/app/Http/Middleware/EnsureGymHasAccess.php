<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Billing;
use App\Models\Gym;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends staff to the billing page when the gym's trial or paid period has ended or it is suspended. */
class EnsureGymHasAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gym = Filament::getTenant();

        if ($gym instanceof Gym && ! $gym->hasAccess()) {
            $billing = Billing::getUrl(tenant: $gym);
            $isBilling = str_starts_with($request->url(), $billing);
            // Livewire update requests for the billing page itself must pass through.
            $isLivewire = $request->hasHeader('X-Livewire') && str_contains((string) $request->header('referer'), '/billing');

            if (! $isBilling && ! $isLivewire) {
                return $request->hasHeader('X-Livewire')
                    ? response()->json(['message' => __('انتهى اشتراك الجيم')], 402)
                    : redirect($billing);
            }
        }

        return $next($request);
    }
}
