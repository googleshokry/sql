<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports\IncomeStatement;
use App\Filament\Resources\ProductResource;
use App\Models\Expense;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Services\Accounting;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $posted = fn () => SalesInvoice::query()->whereNotNull('posted_at');
        $today = (float) $posted()->whereDate('date', today())->sum('total');
        $month = (float) $posted()->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('total');

        $trend = collect(range(6, 0))->map(fn ($d) => (float) $posted()->whereDate('date', today()->subDays($d))->sum('total'))->all();

        $cash = Treasury::query()->with('account')->get()->sum(fn (Treasury $t) => $t->balance());
        $receivable = Accounting::account('receivable')->balance();
        $payable = Accounting::account('payable')->balance();
        $expenses = (float) Expense::query()->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('total');
        $low = Product::query()->lowStock()->count();
        $profit = IncomeStatement::compute(now()->startOfMonth()->toDateString(), now()->toDateString())['net'];

        $f = fn ($v) => number_format($v, 2);

        return [
            Stat::make(__('مبيعات اليوم'), $f($today))->icon(Heroicon::OutlinedShoppingCart)->chart($trend)->color('primary')
                ->description($posted()->whereDate('date', today())->count().__(' فاتورة')),
            Stat::make(__('مبيعات الشهر'), $f($month))->icon(Heroicon::OutlinedPresentationChartLine)->color('success'),
            Stat::make(__('صافي ربح الشهر'), $f($profit))->icon(Heroicon::OutlinedBanknotes)->color($profit >= 0 ? 'success' : 'danger'),
            Stat::make(__('رصيد الخزائن والبنوك'), $f($cash))->icon(Heroicon::OutlinedBuildingLibrary)->color('info'),
            Stat::make(__('مستحقات على العملاء'), $f($receivable))->icon(Heroicon::OutlinedUserGroup)->color('warning'),
            Stat::make(__('مستحقات للموردين'), $f($payable))->icon(Heroicon::OutlinedTruck)->color('danger'),
            Stat::make(__('مصروفات الشهر'), $f($expenses))->icon(Heroicon::OutlinedReceiptPercent)->color('gray'),
            Stat::make(__('أصناف تحت حد الطلب'), $low)->icon(Heroicon::OutlinedExclamationTriangle)->color($low ? 'danger' : 'success')
                ->url(ProductResource::getUrl('index', ['filters' => ['low_stock' => ['isActive' => true]]])),
        ];
    }
}
