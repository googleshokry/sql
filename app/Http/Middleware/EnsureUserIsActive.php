<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rejects API tokens that belong to deactivated users. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json(['message' => __('الحساب موقوف')], 403);
        }

        return $next($request);
    }
}
