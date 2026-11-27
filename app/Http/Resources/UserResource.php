<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => __(User::ROLES[$this->role] ?? $this->role),
            'locale' => $this->locale,
            'branch' => $this->branch?->name,
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'plan' => $this->company->plan?->displayName(),
                'status' => $this->company->status,
                'access_ends_at' => $this->company->accessEndsAt()?->toDateString(),
                'days_left' => $this->company->daysLeft(),
            ] : null,
        ];
    }
}
