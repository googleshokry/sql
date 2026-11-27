<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Subscription;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GymStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $activeMembers = Member::withActiveSubscription()->count();
        $inside = Attendance::whereNull('check_out_at')->count();
        $today = Attendance::whereDate('check_in_at', today())->count();
        $expiring = Subscription::expiringWithin((int) Setting::get('expiry_warning_days'))->count();
        $birthdays = Member::whereMonth('birth_date', today()->month)->whereDay('birth_date', today()->day)->count();

        $attendanceTrend = collect(range(6, 0))->map(fn ($d) => Attendance::whereDate('check_in_at', today()->subDays($d))->count())->all();

        $stats = [
            Stat::make('المشتركين الساريين', $activeMembers)
                ->description('من إجمالي '.Member::count().' مشترك')->icon('heroicon-o-users')->color('success'),
            Stat::make('المتواجدون الآن', $inside)
                ->description("حضور اليوم: {$today}")->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->chart($attendanceTrend)->color('primary'),
            Stat::make('اشتراكات تنتهي قريباً', $expiring)
                ->description('خلال '.Setting::get('expiry_warning_days').' أيام')->icon('heroicon-o-clock')->color('warning')
                ->url(\App\Filament\Resources\MemberResource::getUrl('index', ['tab' => 'expiring'])),
            Stat::make('أعياد ميلاد اليوم', $birthdays)->icon('heroicon-o-cake')->color('info'),
        ];

        if (auth()->user()?->isManager()) {
            $todayIncome = (float) Payment::whereDate('paid_at', today())->sum('amount') + (float) Sale::whereDate('sold_at', today())->sum('total');
            $monthIncome = (float) Payment::where('paid_at', '>=', today()->startOfMonth())->sum('amount')
                + (float) Sale::where('sold_at', '>=', today()->startOfMonth())->sum('total');
            $monthExpenses = (float) Expense::where('spent_at', '>=', today()->startOfMonth())->sum('amount');

            $stats[] = Stat::make('إيراد اليوم', Money::format($todayIncome))->icon('heroicon-o-banknotes')->color('success');
            $stats[] = Stat::make('إيراد الشهر', Money::format($monthIncome))
                ->description('مصروفات: '.Money::format($monthExpenses))->icon('heroicon-o-chart-bar')->color('success');
            $stats[] = Stat::make('صافي الشهر', Money::format($monthIncome - $monthExpenses))
                ->icon('heroicon-o-scale')->color($monthIncome >= $monthExpenses ? 'success' : 'danger');
            $stats[] = Stat::make('اشتراكات جديدة هذا الشهر', Subscription::where('created_at', '>=', today()->startOfMonth())->count())
                ->icon('heroicon-o-credit-card');
        }

        return $stats;
    }
}
