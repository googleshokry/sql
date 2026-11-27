<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\User;
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

        $member = Member::where('code', $data['login'])->orWhere('phone', $data['login'])->first();

        if (! $member || ! $member->password || ! Hash::check($data['password'], $member->password)) {
            throw ValidationException::withMessages(['login' => __('بيانات الدخول غير صحيحة')]);
        }
        if ($member->is_blocked) {
            throw ValidationException::withMessages(['login' => __('هذا المشترك محظور')]);
        }

        $member->forceFill(['last_app_login_at' => now()])->save();
        $token = $member->createToken($data['device_name'] ?? 'mobile', ['member'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'type' => 'member',
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

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'mobile', ['staff'])->plainTextToken,
            'type' => 'staff',
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
