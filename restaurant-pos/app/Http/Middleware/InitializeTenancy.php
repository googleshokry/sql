<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحدد المطعم من الرابط /app/{tenant}/... أو /api/{tenant}/... ويحول لقاعدة بياناته.
 * لازم يشتغل قبل تسجيل الدخول (المستخدمين جوه قاعدة بيانات المطعم).
 */
class InitializeTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $tenant = is_string($slug) ? Tenant::where('slug', $slug)->first() : null;

        abort_unless($tenant, 404);

        app(TenantManager::class)->initialize($tenant);

        // الـ controllers مش محتاجة باراميتر المطعم
        $request->route()->forgetParameter('tenant');

        // جلسة مسجلة في مطعم تاني؟ نعتبرها ضيف هنا
        if ($request->hasSession()) {
            $session = $request->session();
            $key = Auth::guard('web')->getName();
            if ($session->has($key) && $session->get('auth_tenant') !== $tenant->slug) {
                $session->forget([$key, 'password_hash_web', 'auth_tenant']);
            }
        }

        return $next($request);
    }
}
