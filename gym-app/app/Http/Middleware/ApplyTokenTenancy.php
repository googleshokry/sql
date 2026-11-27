<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For authenticated API calls the gym comes from the token: a member belongs to one gym,
 * a staff token is issued for one gym ("gym:{id}" ability). Blocks gyms without an active subscription.
 */
class ApplyTokenTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $gym = null;

        if ($user instanceof Member) {
            $gym = Gym::find($user->gym_id);
        } elseif ($user instanceof User) {
            $abilities = $user->currentAccessToken()?->abilities ?? [];
            $gymId = collect($abilities)->first(fn ($a) => str_starts_with($a, 'gym:'));
            $gym = $gymId ? Gym::find((int) substr($gymId, 4)) : null;
            if ($gym && ! $user->canAccessTenant($gym)) {
                $gym = null; // removed from this gym after the token was issued
            }
        }

        if (! $gym) {
            return response()->json(['message' => __('غير مصرح')], 403);
        }
        if (! $gym->hasAccess()) {
            return response()->json(['message' => __('انتهى اشتراك الجيم. برجاء التواصل مع إدارة الجيم.')], 402);
        }

        Tenancy::set($gym);

        return $next($request);
    }
}
