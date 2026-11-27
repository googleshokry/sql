<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Subscription;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a company whose trial or subscription has ended (or was suspended).
 * Panel users are sent to the subscription page; API clients get HTTP 402.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $company = Tenancy::company();

        if ($company === null) {
            return $next($request);
        }

        if (! $company->hasActiveSubscription()) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('انتهى اشتراك الشركة. برجاء تجديد الاشتراك.'), 'subscription_expired' => true], 402);
            }

            if (! $request->routeIs(Subscription::getRouteName(), 'filament.admin.auth.*') && ! $request->is('livewire*')) {
                return redirect()->to(Subscription::getUrl());
            }
        }

        if ($feature !== null && ! $company->hasFeature($feature)) {
            return response()->json(['message' => __('هذه الخاصية غير متاحة في باقتك الحالية.')], 403);
        }

        return $next($request);
    }
}
