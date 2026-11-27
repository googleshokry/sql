<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'is_guest' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Attendance $a) {
            $a->recorded_by ??= auth()->id();
            if ($a->is_guest && ! $a->subscription_id) {
                $sub = Member::find($a->member_id)?->currentSubscription();
                if ($sub) {
                    $a->subscription_id = $sub->id;
                    $sub->increment('guest_invitations_used');
                }
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->check_out_at) {
            return null;
        }
        $minutes = (int) $this->check_in_at->diffInMinutes($this->check_out_at);

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
