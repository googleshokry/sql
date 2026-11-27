<?php

namespace App\Models\Central;

class Plan extends CentralModel
{
    protected $fillable = [
        'name', 'name_en', 'price_monthly', 'price_yearly', 'max_users', 'max_drivers',
        'max_products', 'max_orders_per_month', 'customer_app', 'is_active', 'sort',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'customer_app' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const LIMITS = ['users', 'drivers', 'products', 'orders_per_month'];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? $this->name_en : $this->name;
    }

    /** الحد المسموح (null = بدون حد) */
    public function limit(string $key): ?int
    {
        return $this->{'max_'.$key};
    }

    /** سعر مدة معينة بالشهور (السنوي له سعر خاص) */
    public function priceFor(int $months): float
    {
        if ($months === 12 && (float) $this->price_yearly > 0) {
            return (float) $this->price_yearly;
        }

        return round((float) $this->price_monthly * $months, 2);
    }
}
