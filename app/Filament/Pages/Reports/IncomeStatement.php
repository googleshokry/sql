<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Services\Accounting;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** قائمة الدخل (الأرباح والخسائر) */
class IncomeStatement extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'قائمة الدخل (الأرباح والخسائر)';

    protected static ?string $title = 'قائمة الدخل';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.reports.income-statement';

    public static function compute(string $from, string $to): array
    {
        $bal = fn (string $key) => Accounting::account($key)->balance($from, $to);

        $sales = $bal('sales');
        $returns = -$bal('sales_returns'); // رصيد مدين في حساب إيرادات
        $netSales = $sales - $returns;
        $cogs = $bal('cogs');
        $gross = $netSales - $cogs;

        $otherRevenue = [];
        foreach (Account::query()->where('type', 'revenue')->where('is_group', false)->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['sales', 'sales_returns']))->orderBy('code')->get() as $a) {
            if ($v = $a->balance($from, $to)) {
                $otherRevenue[$a->name] = $v;
            }
        }

        $expenses = [];
        foreach (Account::query()->where('type', 'expense')->where('is_group', false)->where(fn ($q) => $q->where('system_key', '!=', 'cogs')->orWhereNull('system_key'))->orderBy('code')->get() as $a) {
            if ($v = $a->balance($from, $to)) {
                $expenses[$a->name] = $v;
            }
        }

        $net = $gross + array_sum($otherRevenue) - array_sum($expenses);

        return compact('sales', 'returns', 'netSales', 'cogs', 'gross', 'otherRevenue', 'expenses', 'net');
    }

    protected function data(): array
    {
        return static::compute($this->from, $this->to);
    }
}
