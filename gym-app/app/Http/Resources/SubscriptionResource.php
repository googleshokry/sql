<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->plan?->name,
            'plan_color' => $this->plan?->color,
            'trainer' => $this->trainer?->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days_left' => $this->days_left,
            'total_days' => (int) $this->start_date->diffInDays($this->end_date) + 1,
            'status' => $this->status,
            'status_label' => Subscription::statuses()[$this->status] ?? $this->status,
            'sessions_total' => $this->sessions_total,
            'sessions_used' => $this->sessions_used,
            'sessions_left' => $this->sessions_left,
            'freeze_days_allowed' => $this->freeze_days_allowed,
            'freeze_days_used' => $this->freeze_days_used,
            'frozen_from' => $this->frozen_from?->toDateString(),
            'frozen_until' => $this->frozen_until?->toDateString(),
            'guest_invitations' => $this->guest_invitations,
            'guest_invitations_used' => $this->guest_invitations_used,
            'total' => (float) $this->total,
            'paid' => $this->paid_amount,
            'remaining' => $this->remaining_amount,
        ];
    }
}
