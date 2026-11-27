<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** A tenant: one subscribed business with its own isolated data. */
class Company extends Model
{
    public const STATUSES = [
        'trial' => 'تجريبي',
        'active' => 'نشط',
        'suspended' => 'موقوف',
        'cancelled' => 'ملغي',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (blank($company->slug)) {
                $base = Str::slug($company->name) ?: 'company';
                $slug = $base;
                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(4));
                }
                $company->slug = $slug;
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Date until which the company may use the system. */
    public function accessEndsAt(): ?Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    public function hasActiveSubscription(): bool
    {
        if (! in_array($this->status, ['trial', 'active'], true)) {
            return false;
        }

        $endsAt = $this->accessEndsAt();

        return $endsAt === null || $endsAt->isFuture();
    }

    public function daysLeft(): ?int
    {
        $endsAt = $this->accessEndsAt();

        return $endsAt ? max((int) ceil(now()->diffInDays($endsAt, false)), 0) : null;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) $this->plan?->hasFeature($feature);
    }

    /** Whether one more record of a limited kind (users, branches, warehouses) may be created. */
    public function canAdd(string $limit, int $currentCount): bool
    {
        $max = (int) ($this->plan?->{'max_'.$limit} ?? 0);

        return $max === 0 || $currentCount < $max;
    }
}
