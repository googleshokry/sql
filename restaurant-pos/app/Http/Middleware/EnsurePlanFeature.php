<?php

namespace App\Http\Middleware;

use App\Tenancy\PlanLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** ميزة غير متاحة في باقة المطعم (مثال: تطبيق العملاء) */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(PlanLimits::hasFeature($feature), 403, __('الميزة دي مش متاحة في باقة المطعم'));

        return $next($request);
    }
}
