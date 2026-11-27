<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Company;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** SaaS KPIs: companies by status and monthly recurring revenue. */
class PlatformStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $companies = Company::query()->with('plan')->get();
        $paying = $companies->filter(fn (Company $c) => $c->status === 'active' && $c->hasActiveSubscription());
        $mrr = $paying->sum(fn (Company $c) => $c->billing_cycle === 'yearly'
            ? (float) $c->plan?->yearly_price / 12
            : (float) $c->plan?->monthly_price);
        $expiringSoon = $paying->filter(fn (Company $c) => $c->daysLeft() !== null && $c->daysLeft() <= 7)->count();

        return [
            Stat::make(__('الشركات المشتركة'), $companies->count())->icon(Heroicon::OutlinedBuildingOffice2)
                ->description(__('جديد هذا الشهر').': '.$companies->where('created_at', '>=', now()->startOfMonth())->count()),
            Stat::make(__('اشتراكات مدفوعة'), $paying->count())->icon(Heroicon::OutlinedCheckBadge)->color('success'),
            Stat::make(__('فترة تجريبية'), $companies->where('status', 'trial')->filter->hasActiveSubscription()->count())->icon(Heroicon::OutlinedClock)->color('info'),
            Stat::make(__('منتهية أو موقوفة'), $companies->reject->hasActiveSubscription()->count())->icon(Heroicon::OutlinedNoSymbol)->color('danger'),
            Stat::make(__('الإيراد الشهري المتكرر (MRR)'), number_format($mrr, 2))->icon(Heroicon::OutlinedBanknotes)->color('warning'),
            Stat::make(__('تنتهي خلال 7 أيام'), $expiringSoon)->icon(Heroicon::OutlinedExclamationTriangle)->color($expiringSoon ? 'danger' : 'gray'),
        ];
    }
}
