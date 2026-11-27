<?php

namespace App\Filament\Super\Widgets;

use App\Models\Gym;
use App\Models\GymInvoice;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Monthly recurring revenue from paying gyms (yearly plans counted as 1/12).
        $mrr = Gym::where('status', Gym::STATUS_ACTIVE)->with('saasPlan')->get()->sum(fn (Gym $g) => $g->billing_cycle === 'yearly'
            ? (float) $g->saasPlan?->price_yearly / 12
            : (float) $g->saasPlan?->price_monthly);

        return [
            Stat::make(__('إجمالي الجيمات'), Gym::count())->icon('heroicon-o-building-storefront'),
            Stat::make(__('جيمات مشتركة'), Gym::where('status', Gym::STATUS_ACTIVE)->count())->color('success'),
            Stat::make(__('في الفترة التجريبية'), Gym::where('status', Gym::STATUS_TRIAL)->count())->color('info'),
            Stat::make(__('متأخرة / موقوفة'), Gym::whereIn('status', [Gym::STATUS_PAST_DUE, Gym::STATUS_SUSPENDED])->count())->color('danger'),
            Stat::make(__('الإيراد الشهري المتكرر (MRR)'), number_format($mrr, 0))->color('success'),
            Stat::make(__('إيراد هذا الشهر'), number_format((float) GymInvoice::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'), 0)),
            Stat::make(__('فواتير غير مدفوعة'), GymInvoice::where('status', 'unpaid')->count())->color('warning'),
            Stat::make(__('إجمالي المشتركين في كل الجيمات'), Member::count()),
        ];
    }
}
