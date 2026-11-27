<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Subscription plan: limits and enabled modules. */
class Plan extends Model
{
    /** Module keys a plan can enable. */
    public const FEATURES = [
        'pos' => 'نقطة البيع',
        'purchases' => 'المشتريات',
        'multi_warehouse' => 'تعدد المخازن والتحويلات',
        'installments' => 'التقسيط',
        'crm' => 'متابعة العملاء (CRM)',
        'hr' => 'الموارد البشرية والرواتب',
        'accounting' => 'المحاسبة والتقارير المالية',
        'mobile_app' => 'تطبيق الموبايل',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }
}
