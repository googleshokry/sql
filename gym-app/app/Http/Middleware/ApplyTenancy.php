<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Makes the panel's current gym the tenant for all model queries in this request. */
class ApplyTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenancy::set(Filament::getTenant());

        return $next($request);
    }
}
