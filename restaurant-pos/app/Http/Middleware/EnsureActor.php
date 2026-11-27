<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * actor:customer        => عميل
 * actor:admin,cashier   => موظف بصلاحية من المذكورة
 */
class EnsureActor
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->user();

        $allowed = match (true) {
            $actor instanceof Customer => in_array('customer', $roles, true),
            $actor instanceof User => $actor->is_active && in_array($actor->role->value, $roles, true),
            default => false,
        };

        abort_unless($allowed, 403, __('غير مسموح'));

        return $next($request);
    }
}
