<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        // A token moves to whoever logged in last on that phone.
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_used_at' => now()],
        );

        return response()->json(['message' => 'ok'], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return response()->json(null, 204);
    }
}
