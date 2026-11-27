<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenants = Tenant::with('plan')->get();
        $states = $tenants->groupBy(fn (Tenant $t) => $t->accessState())->map->count();
        $mrr = $tenants->filter(fn (Tenant $t) => $t->accessState() === 'active')->sum(fn (Tenant $t) => (float) $t->plan?->price_monthly);
        $month = (float) Payment::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount');
        $expiring = $tenants->filter(fn (Tenant $t) => $t->canOperate() && $t->daysLeft() <= 7)->count();

        return [
            Stat::make(__('المطاعم'), $tenants->count())
                ->description(__('جديد هذا الشهر: :n', ['n' => $tenants->where('created_at', '>=', now()->startOfMonth())->count()])),
            Stat::make(__('مشتركين'), $states['active'] ?? 0)->color('success')
                ->description(__('تجريبي: :t · منتهي: :e · موقوف: :s', ['t' => $states['trial'] ?? 0, 'e' => $states['expired'] ?? 0, 's' => $states['suspended'] ?? 0])),
            Stat::make(__('الإيراد الشهري المتوقع (MRR)'), number_format($mrr).' '.__('ج.م')),
            Stat::make(__('تحصيل الشهر'), number_format($month).' '.__('ج.م'))->color('success'),
            Stat::make(__('مدفوعات تنتظر المراجعة'), Payment::where('status', 'pending')->where('method', '!=', 'paymob')->count())->color('warning'),
            Stat::make(__('ينتهي خلال 7 أيام'), $expiring)->color('danger'),
        ];
    }
}
