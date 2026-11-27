<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    /** بعد المدة دي من غير تحديث للموقع بنعتبر الطيار غير متصل */
    public const STALE_MINUTES = 10;

    protected $fillable = [
        'user_id', 'name', 'phone', 'is_active', 'is_online',
        'latitude', 'longitude', 'location_accuracy', 'location_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn () => \App\Tenancy\PlanLimits::ensure('drivers'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders()
    {
        return $this->hasMany(Order::class)->whereIn('status', OrderStatus::open());
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class);
    }

    /** متصل = فاتح الشيفت وموقعه متحدث قريب */
    public function isLive(): bool
    {
        return $this->is_online && $this->location_updated_at?->gt(now()->subMinutes(self::STALE_MINUTES));
    }

    public function recordLocation(float $lat, float $lng, ?float $accuracy = null, ?float $speed = null): void
    {
        $this->update([
            'latitude' => $lat,
            'longitude' => $lng,
            'location_accuracy' => $accuracy,
            'location_updated_at' => now(),
            'is_online' => true,
        ]);

        // نسجل نقطة في خط السير كل 30 ثانية على الأكثر
        $last = $this->locations()->latest('id')->value('created_at');
        if (! $last || now()->diffInSeconds($last, true) >= 30) {
            $this->locations()->create(['latitude' => $lat, 'longitude' => $lng, 'accuracy' => $accuracy, 'speed' => $speed]);
        }
    }

    public function locationPayload(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'accuracy' => $this->location_accuracy,
            'updated_at' => $this->location_updated_at?->toIso8601String(),
            'live' => $this->isLive(),
        ];
    }
}
