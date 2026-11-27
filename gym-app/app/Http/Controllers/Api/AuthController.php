<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Member login with membership code or phone + app password. */
    public function memberLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Scoped to the gym named in the X-Gym header (see ResolveGymFromRequest).
        $member = Member::where(fn ($q) => $q->where('code', $data['login'])->orWhere('phone', $data['login']))->first();

        if (! $member || ! $member->password || ! Hash::check($data['password'], $member->password)) {
            throw ValidationException::withMessages(['login' => __('بيانات الدخول غير صحيحة')]);
        }
        if ($member->is_blocked) {
            throw ValidationException::withMessages(['login' => __('هذا المشترك محظور')]);
        }
        if (! Tenancy::gym()->hasAccess()) {
            throw ValidationException::withMessages(['login' => __('انتهى اشتراك الجيم. برجاء التواصل مع إدارة الجيم.')]);
        }

        $member->forceFill(['last_app_login_at' => now()])->save();
        $token = $member->createToken($data['device_name'] ?? 'mobile', ['member'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'type' => 'member',
            'gym' => ['slug' => Tenancy::gym()->slug, 'name' => Tenancy::gym()->name],
            'member' => new MemberResource($member->load('trainer')),
        ]);
    }

    /** Staff (receptionist / manager) login with email + password. */
    public function staffLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('بيانات الدخول غير صحيحة')]);
        }

        // Gym from the X-Gym header, or the user's only gym.
        $gym = Tenancy::gym() ?? ($user->gyms()->count() === 1 ? $user->gyms()->first() : null);
        if (! $gym || ! $user->canAccessTenant($gym)) {
            throw ValidationException::withMessages(['gym' => __('كود الجيم غير صحيح')]);
        }
        if (! $gym->hasAccess()) {
            throw ValidationException::withMessages(['gym' => __('انتهى اشتراك الجيم. برجاء التواصل مع إدارة الجيم.')]);
        }
        Tenancy::set($gym);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'mobile', ['staff', 'gym:'.$gym->id])->plainTextToken,
            'type' => 'staff',
            'gym' => ['slug' => $gym->slug, 'name' => $gym->name],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => User::roles()[$user->role] ?? $user->role,
                'is_manager' => $user->isManager(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('تم تسجيل الخروج')]);
    }
}
