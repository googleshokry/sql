<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public / login API calls name their gym with the "X-Gym" header (the gym code/slug). */
class ResolveGymFromRequest
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $slug = trim((string) ($request->header('X-Gym') ?: $request->query('gym', '')));
        $gym = $slug !== '' ? Gym::where('slug', strtolower($slug))->first() : null;

        if (! $gym && $mode === 'required') {
            return response()->json(['message' => __('كود الجيم غير صحيح'), 'errors' => ['gym' => [__('كود الجيم غير صحيح')]]], 422);
        }

        Tenancy::set($gym);

        return $next($request);
    }
}
