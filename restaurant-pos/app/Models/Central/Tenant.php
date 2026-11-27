<?php

namespace App\Models\Central;

/**
 * مطعم مشترك في المنصة — له قاعدة بيانات خاصة.
 */
class Tenant extends CentralModel
{
    protected $fillable = [
        'name', 'slug', 'database', 'owner_name', 'owner_email', 'owner_phone', 'plan_id',
        'status', 'trial_ends_at', 'subscription_ends_at', 'suspended_reason', 'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'settings' => 'array',
    ];

    /** إعدادات المطعم الافتراضية (بتتكتب فوق config('pos.*')) */
    public const DEFAULT_SETTINGS = [
        'currency' => 'ج.م',
        'tax_rate' => 0,
        'service_rate' => 0,
        'restaurant_phone' => '',
        'restaurant_address' => '',
        'receipt_footer' => 'شكراً لزيارتكم',
        'map_lat' => 30.0444,
        'map_lng' => 31.2357,
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key]
            ?? config('pos_defaults.'.$key)
            ?? self::DEFAULT_SETTINGS[$key]
            ?? $default;
    }

    /** trial | active | expired | suspended */
    public function accessState(): string
    {
        if ($this->status === 'suspended') {
            return 'suspended';
        }
        if ($this->status === 'trial') {
            return $this->trial_ends_at?->isFuture() ? 'trial' : 'expired';
        }

        return $this->subscription_ends_at?->isFuture() ? 'active' : 'expired';
    }

    public function canOperate(): bool
    {
        return in_array($this->accessState(), ['trial', 'active'], true);
    }

    public function endsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    public function daysLeft(): int
    {
        $end = $this->endsAt();

        return $end && $end->isFuture() ? (int) ceil(now()->diffInHours($end, true) / 24) : 0;
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'trial' => __('فترة تجريبية'),
            'active' => __('مشترك'),
            'expired' => __('منتهي'),
            'suspended' => __('موقوف'),
            default => $state,
        };
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            'trial' => 'info',
            'active' => 'success',
            'expired' => 'warning',
            'suspended' => 'danger',
            default => 'gray',
        };
    }

    /** تجديد / تفعيل الاشتراك: يبدأ من نهاية الاشتراك الحالي لو لسه شغال */
    public function extendSubscription(?Plan $plan, int $months): void
    {
        $start = $this->status === 'active' && $this->subscription_ends_at?->isFuture()
            ? $this->subscription_ends_at
            : now();

        $this->update([
            'plan_id' => $plan?->id ?? $this->plan_id,
            'status' => 'active',
            'subscription_ends_at' => $start->copy()->addMonths($months),
            'suspended_reason' => null,
        ]);
    }

    public function url(string $path = ''): string
    {
        return url('app/'.$this->slug.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}
