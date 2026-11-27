<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use BelongsToGym;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'ساري',
        self::STATUS_UPCOMING => 'لم يبدأ',
        self::STATUS_FROZEN => 'مجمد',
        self::STATUS_EXPIRED => 'منتهي',
        self::STATUS_CANCELLED => 'ملغي',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_UPCOMING => 'info',
        self::STATUS_FROZEN => 'warning',
        self::STATUS_EXPIRED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    /** Translated labels. */
    public static function statuses(): array
    {
        return array_map(fn (string $label) => __($label), self::STATUSES);
    }

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'frozen_from' => 'date',
        'frozen_until' => 'date',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Subscription $s) {
            $s->discount ??= 0;
            $s->total = max(0, (float) $s->price - (float) $s->discount);
            if ($s->status !== self::STATUS_CANCELLED) {
                $s->status = $s->computeStatus();
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(SubscriptionFreeze::class)->latest();
    }

    public function computeStatus(): string
    {
        $today = today();

        if ($this->frozen_from && $this->frozen_until
            && $today->between($this->frozen_from, $this->frozen_until)) {
            return self::STATUS_FROZEN;
        }
        if ($this->end_date && $this->end_date->lt($today)) {
            return self::STATUS_EXPIRED;
        }
        if ($this->sessions_total && $this->sessions_used >= $this->sessions_total) {
            return self::STATUS_EXPIRED;
        }
        if ($this->start_date && $this->start_date->gt($today)) {
            return self::STATUS_UPCOMING;
        }

        return self::STATUS_ACTIVE;
    }

    public function refreshStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }
        $new = $this->computeStatus();
        if ($new !== $this->status) {
            $this->status = $new;
            $this->saveQuietly();
        }
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount'));
    }

    public function getRemainingAmountAttribute(): float
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0;
        }

        return max(0, round((float) $this->total - $this->paid_amount, 2));
    }

    public function getDaysLeftAttribute(): int
    {
        return max(0, (int) today()->diffInDays($this->end_date, false));
    }

    public function getSessionsLeftAttribute(): ?int
    {
        return $this->sessions_total ? max(0, $this->sessions_total - $this->sessions_used) : null;
    }

    public function getFreezeDaysLeftAttribute(): int
    {
        return max(0, $this->freeze_days_allowed - $this->freeze_days_used);
    }

    /** Freeze the subscription for N days; extends the end date accordingly. */
    public function freeze(int $days, Carbon $from, ?string $reason = null): SubscriptionFreeze
    {
        if ($days < 1 || $days > $this->freeze_days_left) {
            throw new \InvalidArgumentException(__('عدد أيام التجميد غير مسموح به'));
        }

        $until = $from->copy()->addDays($days - 1);

        $freeze = $this->freezes()->create([
            'start_date' => $from,
            'end_date' => $until,
            'days' => $days,
            'reason' => $reason,
            'created_by' => auth()->id(),
        ]);

        $this->frozen_from = $from;
        $this->frozen_until = $until;
        $this->freeze_days_used += $days;
        $this->end_date = $this->end_date->copy()->addDays($days);
        $this->save();

        return $freeze;
    }

    /** End the current/upcoming freeze early and give back the unused days. */
    public function unfreeze(): void
    {
        if (! $this->frozen_from || ! $this->frozen_until || $this->frozen_until->lt(today())) {
            return;
        }

        $resumeFrom = $this->frozen_from->gt(today()) ? $this->frozen_from : today();
        $unused = (int) $resumeFrom->diffInDays($this->frozen_until) + 1;

        $this->end_date = $this->end_date->copy()->subDays($unused);
        $this->freeze_days_used = max(0, $this->freeze_days_used - $unused);

        $freeze = $this->freezes()->first();
        if ($resumeFrom->equalTo($this->frozen_from)) {
            $freeze?->delete();
            $this->frozen_from = null;
            $this->frozen_until = null;
        } else {
            $this->frozen_until = today()->subDay();
            $freeze?->update(['end_date' => $this->frozen_until, 'days' => max(0, $freeze->days - $unused)]);
        }

        $this->save();
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_FROZEN])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today());
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays($days));
    }
}
