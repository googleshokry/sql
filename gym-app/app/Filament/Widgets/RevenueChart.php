<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'الإيرادات والمصروفات (آخر 12 شهر)';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => today()->startOfMonth()->subMonthsNoOverflow($i));

        $income = $months->map(fn ($m) => round(
            (float) Payment::whereBetween('paid_at', [$m, $m->copy()->endOfMonth()])->sum('amount')
            + (float) Sale::whereBetween('sold_at', [$m, $m->copy()->endOfMonth()])->sum('total'), 2));
        $expenses = $months->map(fn ($m) => round((float) Expense::whereBetween('spent_at', [$m, $m->copy()->endOfMonth()])->sum('amount'), 2));

        return [
            'datasets' => [
                ['label' => 'الإيرادات', 'data' => $income->all(), 'backgroundColor' => '#22c55e', 'borderColor' => '#22c55e'],
                ['label' => 'المصروفات', 'data' => $expenses->all(), 'backgroundColor' => '#ef4444', 'borderColor' => '#ef4444'],
            ],
            'labels' => $months->map(fn ($m) => $m->format('Y-m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
