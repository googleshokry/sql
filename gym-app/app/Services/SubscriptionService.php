<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a subscription for a member from a plan, optionally recording an upfront payment.
     *
     * @param  array{plan_id:int, start_date?:string|null, discount?:float|null, trainer_id?:int|null, paid_amount?:float|null, method?:string|null, notes?:string|null, price?:float|null}  $data
     */
    public static function create(Member $member, array $data): Subscription
    {
        return DB::transaction(function () use ($member, $data) {
            $plan = Plan::findOrFail($data['plan_id']);
            $start = Carbon::parse($data['start_date'] ?? today());

            $subscription = $member->subscriptions()->create([
                'plan_id' => $plan->id,
                'trainer_id' => $data['trainer_id'] ?? $member->trainer_id,
                'start_date' => $start,
                'end_date' => $start->copy()->addDays($plan->duration_days - 1),
                'price' => $data['price'] ?? $plan->price,
                'discount' => $data['discount'] ?? 0,
                'sessions_total' => $plan->sessions_count,
                'freeze_days_allowed' => $plan->freeze_days,
                'guest_invitations' => $plan->guest_invitations,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $paid = (float) ($data['paid_amount'] ?? 0);
            if ($paid > 0) {
                $subscription->payments()->create([
                    'member_id' => $member->id,
                    'amount' => min($paid, (float) $subscription->total),
                    'method' => $data['method'] ?? 'cash',
                    'type' => 'subscription',
                    'paid_at' => now(),
                ]);
            }

            return $subscription;
        });
    }

    /** Start date for a renewal: the day after the member's latest subscription ends (or today). */
    public static function nextStartDate(Member $member): Carbon
    {
        $last = $member->subscriptions()->where('status', '!=', Subscription::STATUS_CANCELLED)->max('end_date');

        return $last && Carbon::parse($last)->gte(today()) ? Carbon::parse($last)->addDay() : today();
    }

    public static function addPayment(Subscription $subscription, float $amount, string $method = 'cash', ?string $notes = null): Payment
    {
        return $subscription->payments()->create([
            'member_id' => $subscription->member_id,
            'amount' => $amount,
            'method' => $method,
            'type' => 'subscription',
            'paid_at' => now(),
            'notes' => $notes,
        ]);
    }

    /** Refresh statuses of all non-cancelled subscriptions (run daily by the scheduler). */
    public static function refreshAll(): int
    {
        $changed = 0;
        Subscription::query()
            ->where('status', '!=', Subscription::STATUS_CANCELLED)
            ->where(fn ($q) => $q->where('status', '!=', Subscription::STATUS_EXPIRED)->orWhereDate('end_date', '>=', today()))
            ->chunkById(500, function ($subs) use (&$changed) {
                foreach ($subs as $s) {
                    $before = $s->status;
                    $s->refreshStatus();
                    $changed += (int) ($before !== $s->status);
                }
            });

        return $changed;
    }
}
