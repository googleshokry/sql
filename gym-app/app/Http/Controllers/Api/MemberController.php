<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MeasurementResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Endpoints for the logged-in member (mobile app). */
class MemberController extends Controller
{
    private function member(Request $request): Member
    {
        return $request->user();
    }

    public function me(Request $request): MemberResource
    {
        return new MemberResource($this->member($request)->load('trainer'));
    }

    /** Everything the home screen needs in one call. */
    public function home(Request $request): JsonResponse
    {
        $m = $this->member($request)->load('trainer');
        $visits = $m->attendances();

        return response()->json([
            'member' => new MemberResource($m),
            'stats' => [
                'visits_total' => (clone $visits)->count(),
                'visits_this_month' => (clone $visits)->where('check_in_at', '>=', today()->startOfMonth())->count(),
                'visits_last_7_days' => collect(range(6, 0))->map(fn ($d) => [
                    'date' => today()->subDays($d)->toDateString(),
                    'count' => $m->attendances()->whereDate('check_in_at', today()->subDays($d))->count(),
                ])->values(),
                'last_visit' => (clone $visits)->value('check_in_at')?->toIso8601String(),
            ],
        ]);
    }

    public function subscriptions(Request $request): AnonymousResourceCollection
    {
        return SubscriptionResource::collection(
            $this->member($request)->subscriptions()->with(['plan', 'trainer'])->withSum('payments', 'amount')->get()
        );
    }

    public function attendances(Request $request): AnonymousResourceCollection
    {
        return AttendanceResource::collection($this->member($request)->attendances()->paginate(20));
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->member($request)->payments()->with('subscription.plan')->paginate(20));
    }

    public function measurements(Request $request): AnonymousResourceCollection
    {
        return MeasurementResource::collection($this->member($request)->measurements()->get());
    }

    public function updateProfile(Request $request): MemberResource
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', 'in:ar,en'],
        ]);
        $member = $this->member($request);
        $member->update(array_filter($data, fn ($v) => $v !== null));

        return new MemberResource($member->fresh('trainer'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        $member = $this->member($request);

        if (! Hash::check($data['current_password'], $member->password)) {
            throw ValidationException::withMessages(['current_password' => __('كلمة المرور الحالية غير صحيحة')]);
        }

        $member->forceFill(['password' => $data['password']])->save();
        $member->tokens()->where('id', '!=', $member->currentAccessToken()->id)->delete();

        return response()->json(['message' => __('تم تغيير كلمة المرور')]);
    }
}
