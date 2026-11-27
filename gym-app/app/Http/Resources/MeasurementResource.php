<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BodyMeasurement */
class MeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $n = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => $this->id,
            'measured_at' => $this->measured_at->toDateString(),
            'weight' => $n($this->weight),
            'height' => $n($this->height),
            'bmi' => $this->bmi,
            'body_fat' => $n($this->body_fat),
            'muscle_mass' => $n($this->muscle_mass),
            'chest' => $n($this->chest),
            'waist' => $n($this->waist),
            'hips' => $n($this->hips),
            'arms' => $n($this->arms),
            'thighs' => $n($this->thighs),
            'notes' => $this->notes,
        ];
    }
}
