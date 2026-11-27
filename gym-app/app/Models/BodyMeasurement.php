<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['measured_at' => 'date'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getBmiAttribute(): ?float
    {
        if (! $this->weight || ! $this->height) {
            return null;
        }
        $m = $this->height / 100;

        return round($this->weight / ($m * $m), 1);
    }
}
