<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MemberResource;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Endpoints for reception staff (scan QR / check members in and out). */
class StaffController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $data = [
            'active_members' => Member::withActiveSubscription()->count(),
            'inside_now' => Attendance::whereNull('check_out_at')->count(),
            'visits_today' => Attendance::whereDate('check_in_at', today())->count(),
            'expiring_soon' => Subscription::expiringWithin((int) Setting::get('expiry_warning_days'))->count(),
        ];

        if ($request->user()->isManager()) {
            $data['income_today'] = round((float) Payment::whereDate('paid_at', today())->sum('amount')
                + (float) Sale::whereDate('sold_at', today())->sum('total'), 2);
        }

        return response()->json($data);
    }

    /** Toggle check-in / check-out by member code (QR / barcode content), phone or national id. */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'force' => ['nullable', 'boolean'],
        ]);

        $member = Member::where('code', $data['code'])->orWhere('phone', $data['code'])
            ->orWhere('national_id', $data['code'])->first();

        if (! $member) {
            return response()->json(['ok' => false, 'action' => null, 'message' => __('لا يوجد مشترك بهذا الكود / الرقم'), 'member' => null], 404);
        }

        $force = ($data['force'] ?? false) && $request->user()->isManager();
        $result = $force && ! $member->openAttendance()
            ? AttendanceService::checkIn($member, true)
            : AttendanceService::toggle($member);

        return response()->json([
            'ok' => $result['ok'],
            'action' => $result['action'],
            'message' => $result['message'],
            'can_force' => ! $result['ok'] && $request->user()->isManager(),
            'member' => new MemberResource($member->fresh('trainer')),
        ], $result['ok'] ? 200 : 422);
    }

    public function members(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('search', ''));

        return MemberResource::collection(
            Member::query()->with(['trainer', 'latestSubscription.plan'])
                ->when($term !== '', fn ($q) => $q->search($term))
                ->latest('id')->paginate(20)
        );
    }

    public function member(Member $member): MemberResource
    {
        return new MemberResource($member->load('trainer'));
    }

    public function inside(): AnonymousResourceCollection
    {
        return AttendanceResource::collection(
            Attendance::with('member')->whereNull('check_out_at')->latest('check_in_at')->get()
        );
    }

    public function checkout(Attendance $attendance): JsonResponse
    {
        if (! $attendance->check_out_at) {
            $attendance->update(['check_out_at' => now()]);
        }

        return response()->json(['message' => __('تم تسجيل الانصراف - مدة التمرين :duration', ['duration' => $attendance->duration])]);
    }
}
