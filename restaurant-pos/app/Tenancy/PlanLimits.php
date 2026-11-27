<?php

namespace App\Tenancy;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** حدود الباقة للمطعم الحالي */
class PlanLimits
{
    public static function limit(string $key): ?int
    {
        return tenant()?->plan?->limit($key);
    }

    public static function usage(string $key): int
    {
        return match ($key) {
            // حسابات الطيارين مش محسوبة ضمن المستخدمين (ليها حد منفصل)
            'users' => User::where('role', '!=', UserRole::Driver)->count(),
            'drivers' => Driver::count(),
            'products' => Product::count(),
            'orders_per_month' => Order::where('created_at', '>=', now()->startOfMonth())->count(),
        };
    }

    public static function reached(string $key): bool
    {
        $limit = static::limit($key);

        return $limit !== null && static::usage($key) >= $limit;
    }

    public static function message(string $key): string
    {
        $labels = [
            'users' => __('المستخدمين'),
            'drivers' => __('الطيارين'),
            'products' => __('الأصناف'),
            'orders_per_month' => __('طلبات الشهر'),
        ];

        return __('وصلت للحد الأقصى من :what في باقتك (:limit). رقّي الباقة من صفحة الاشتراك.', [
            'what' => $labels[$key], 'limit' => static::limit($key),
        ]);
    }

    public static function ensure(string $key): void
    {
        if (tenant() && static::reached($key)) {
            throw ValidationException::withMessages(['limit' => static::message($key)]);
        }
    }

    public static function hasFeature(string $feature): bool
    {
        return (bool) (tenant()?->plan?->{$feature} ?? true);
    }
}
