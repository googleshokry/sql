<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected ?string $heading = 'المبيعات والمشتريات والمصروفات - آخر 12 شهر';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $sum = fn (string $model, $m, string $col = 'total') => (float) $model::query()
            ->when($model !== Expense::class, fn ($q) => $q->whereNotNull('posted_at'))
            ->whereYear('date', $m->year)->whereMonth('date', $m->month)->sum($col);

        return [
            'datasets' => [
                ['label' => __('المبيعات'), 'data' => $months->map(fn ($m) => $sum(SalesInvoice::class, $m))->all(), 'borderColor' => '#4f46e5', 'backgroundColor' => 'rgba(79,70,229,.15)', 'fill' => true, 'tension' => .3],
                ['label' => __('المشتريات'), 'data' => $months->map(fn ($m) => $sum(PurchaseInvoice::class, $m))->all(), 'borderColor' => '#f59e0b', 'backgroundColor' => 'transparent', 'tension' => .3],
                ['label' => __('المصروفات'), 'data' => $months->map(fn ($m) => $sum(Expense::class, $m))->all(), 'borderColor' => '#ef4444', 'backgroundColor' => 'transparent', 'tension' => .3],
            ],
            'labels' => $months->map(fn ($m) => $m->format('Y-m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
