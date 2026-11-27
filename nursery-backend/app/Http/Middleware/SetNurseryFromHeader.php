<?php

namespace App\Http\Middleware;

use App\Models\Nursery;
use App\Support\CurrentNursery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API tenancy: the mobile app sends X-Nursery-Id on every request.
 * The user must be a member of that nursery, and the nursery must be active.
 */
class SetNurseryFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Nursery-Id');

        abort_if(blank($id) || ! ctype_digit((string) $id), 400, __('api.nursery_header_required'));

        $nursery = Nursery::query()->find((int) $id);
        $user = $request->user();

        // Same 404 whether the nursery does not exist or the user is not a member: no enumeration.
        abort_if(! $nursery || ! $user || ! $user->belongsToNursery($nursery), 404);
        abort_if(! $nursery->isActive(), 403, __('api.nursery_inactive'));

        app(CurrentNursery::class)->set($nursery);

        return $next($request);
    }
}
