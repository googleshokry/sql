<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Subscription;

class AttendanceService
{
    /**
     * Check a member in or out. Returns [ok(bool), action('in'|'out'|null), message, attendance|null].
     *
     * @return array{ok:bool, action:?string, message:string, attendance:?Attendance}
     */
    public static function toggle(Member $member, bool $force = false): array
    {
        $open = $member->openAttendance();
        if ($open) {
            $open->update(['check_out_at' => now()]);

            return ['ok' => true, 'action' => 'out', 'message' => __('تم تسجيل الانصراف - مدة التمرين :duration', ['duration' => $open->duration]), 'attendance' => $open];
        }

        return static::checkIn($member, $force);
    }

    /** @return array{ok:bool, action:?string, message:string, attendance:?Attendance} */
    public static function checkIn(Member $member, bool $force = false): array
    {
        $fail = fn (string $msg) => ['ok' => false, 'action' => null, 'message' => $msg, 'attendance' => null];

        if ($member->is_blocked && ! $force) {
            return $fail(__('هذا المشترك محظور').($member->blocked_reason ? ': '.$member->blocked_reason : ''));
        }

        if ($member->openAttendance()) {
            return $fail(__('المشترك مسجل دخول بالفعل'));
        }

        $subscription = $member->currentSubscription();
        if ($subscription) {
            $subscription->refreshStatus();
        }

        if (! $force) {
            if (! $subscription) {
                return $fail(__('لا يوجد اشتراك ساري لهذا المشترك'));
            }
            if ($subscription->status === Subscription::STATUS_FROZEN) {
                return $fail(__('الاشتراك مجمد حتى :date', ['date' => $subscription->frozen_until->format('Y-m-d')]));
            }
            if ($subscription->status === Subscription::STATUS_EXPIRED) {
                return $fail(__('الاشتراك منتهي (انتهت الحصص أو المدة)'));
            }
            if (! Setting::get('allow_checkin_with_debt') && $subscription->remaining_amount > 0) {
                return $fail(__('يوجد مبلغ متبقي على المشترك: :amount', ['amount' => $subscription->remaining_amount]));
            }
        }

        $attendance = $member->attendances()->create([
            'subscription_id' => $subscription?->id,
            'check_in_at' => now(),
            'recorded_by' => auth()->id(),
        ]);

        if ($subscription && $subscription->sessions_total) {
            $subscription->increment('sessions_used');
            $subscription->refresh()->refreshStatus();
        }

        $msg = __('تم تسجيل الدخول بنجاح');
        if ($subscription) {
            $msg .= __(' - متبقي :days يوم', ['days' => $subscription->days_left]);
            if ($subscription->sessions_total) {
                $msg .= __(' و :sessions حصة', ['sessions' => $subscription->sessions_left]);
            }
        }

        return ['ok' => true, 'action' => 'in', 'message' => $msg, 'attendance' => $attendance];
    }

    /** Close attendances left open longer than the configured hours. */
    public static function autoCheckout(): int
    {
        $hours = (int) Setting::get('auto_checkout_hours', 4);
        $count = 0;
        Attendance::whereNull('check_out_at')->where('check_in_at', '<=', now()->subHours($hours))
            ->each(function (Attendance $a) use ($hours, &$count) {
                $a->update(['check_out_at' => $a->check_in_at->copy()->addHours($hours), 'notes' => trim($a->notes.' '.__('انصراف تلقائي'))]);
                $count++;
            });

        return $count;
    }
}
