<?php

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Member */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current = $this->currentSubscription() ?? $this->latestSubscription;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->age,
            'photo_url' => $this->photo_url,
            'height' => $this->height ? (float) $this->height : null,
            'weight' => $this->weight ? (float) $this->weight : null,
            'trainer' => $this->trainer ? ['name' => $this->trainer->name, 'phone' => $this->trainer->phone] : null,
            'joined_at' => $this->joined_at?->toDateString(),
            'is_blocked' => $this->is_blocked,
            'locale' => $this->locale,
            'is_inside' => (bool) $this->openAttendance(),
            'balance' => $this->balance,
            'subscription' => $current ? new SubscriptionResource($current->loadMissing('plan', 'trainer')) : null,
        ];
    }
}
