<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /**
     * Only phones already registered by a nursery can log in (no self sign-up).
     * The response is the same either way, so phone numbers can't be enumerated.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = OtpService::normalizePhone($data['phone']);

        if (User::where('phone', $phone)->exists()) {
            $this->otp->send($phone);
        }

        return response()->json(['message' => __('api.otp_sent'), 'expires_in' => OtpService::TTL_SECONDS]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $phone = OtpService::normalizePhone($data['phone']);
        $user = User::where('phone', $phone)->first();

        if (! $user || ! $this->otp->verify($phone, $data['code'])) {
            throw ValidationException::withMessages(['code' => __('api.otp_invalid')]);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new MeResource($user->load('nurseries')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('api.logged_out')]);
    }
}
