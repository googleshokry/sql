<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Attendance */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member' => $this->whenLoaded('member', fn () => ['id' => $this->member->id, 'name' => $this->member->name, 'code' => $this->member->code, 'photo_url' => $this->member->photo ? $this->member->photo_url : null]),
            'check_in_at' => $this->check_in_at->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'duration' => $this->duration,
            'is_guest' => $this->is_guest,
            'guest_name' => $this->guest_name,
        ];
    }
}
