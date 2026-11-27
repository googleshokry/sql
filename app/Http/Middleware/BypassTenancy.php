<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets the platform (super admin) panel read data across all companies. */
class BypassTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        return Tenancy::withoutTenancy(fn () => $next($request));
    }
}
