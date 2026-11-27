<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A tenant: one gym (or branch) subscribed to the SaaS. */
class Gym extends Model implements HasAvatar, HasName
{
    use SoftDeletes;

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TRIAL => 'فترة تجريبية',
        self::STATUS_ACTIVE => 'مشترك',
        self::STATUS_PAST_DUE => 'متأخر في الدفع',
        self::STATUS_SUSPENDED => 'موقوف',
        self::STATUS_CANCELLED => 'ملغي',
    ];

    public const STATUS_COLORS = [
        self::STATUS_TRIAL => 'info',
        self::STATUS_ACTIVE => 'success',
        self::STATUS_PAST_DUE => 'warning',
        self::STATUS_SUSPENDED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return array_map(fn (string $label) => __($label), self::STATUSES);
    }

    protected static function booted(): void
    {
        static::creating(function (Gym $gym) {
            $gym->slug = static::uniqueSlug($gym->slug ?: $gym->name);
            $gym->status ??= self::STATUS_TRIAL;
            $gym->trial_ends_at ??= now()->addDays((int) config('saas.trial_days', 14))->endOfDay();
        });
        static::saved(fn (Gym $gym) => Setting::flush($gym->id));
    }

    public static function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'gym';
        if (strlen($base) < 3) {
            $base .= '-gym';
        }
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, ['new', 'admin', 'super', 'api', 'login', 'register'], true)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    // ---- Relationships (names follow Filament's tenant relationship convention) ----

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function saasPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(GymInvoice::class)->latest('period_start');
    }

    public function members(): HasMany { return $this->hasMany(Member::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
    public function trainers(): HasMany { return $this->hasMany(Trainer::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function expenseCategories(): HasMany { return $this->hasMany(ExpenseCategory::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
    public function announcements(): HasMany { return $this->hasMany(Announcement::class); }
    public function settings(): HasMany { return $this->hasMany(Setting::class); }

    // ---- SaaS subscription state ----

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL && $this->trial_ends_at?->isFuture();
    }

    /** Whether staff and members may use the gym (trial running or paid period running). */
    public function hasAccess(): bool
    {
        return match ($this->status) {
            self::STATUS_TRIAL => (bool) $this->trial_ends_at?->isFuture(),
            self::STATUS_ACTIVE, self::STATUS_PAST_DUE => (bool) $this->subscription_ends_at?->isFuture(),
            default => false,
        };
    }

    public function accessEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->status === self::STATUS_TRIAL ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    public function daysLeft(): int
    {
        $end = $this->accessEndsAt();

        return $end ? max(0, (int) ceil(now()->diffInDays($end, false))) : 0;
    }

    /** Limit check, e.g. canAdd('max_members', $currentCount). */
    public function withinLimit(string $limit, int $current): bool
    {
        $max = $this->saasPlan?->{$limit};

        return $max === null || $current < $max;
    }

    /** Extend the paid period after a payment and mark the gym active. */
    public function extendSubscription(string $cycle, ?SaasPlan $plan = null): void
    {
        $from = $this->subscription_ends_at?->isFuture() ? $this->subscription_ends_at : now();
        $this->forceFill([
            'saas_plan_id' => $plan?->id ?? $this->saas_plan_id,
            'billing_cycle' => $cycle,
            'status' => self::STATUS_ACTIVE,
            'subscription_ends_at' => $cycle === 'yearly' ? $from->copy()->addYear() : $from->copy()->addMonthNoOverflow(),
            'suspended_reason' => null,
        ])->save();
    }

    // ---- Filament ----

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : \App\Support\Avatar::for($this->name, '#f59e0b');
    }
}
