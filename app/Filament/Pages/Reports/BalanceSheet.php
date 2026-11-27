<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** الميزانية العمومية (المركز المالي) */
class BalanceSheet extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'الميزانية العمومية';

    protected static ?string $title = 'الميزانية العمومية (قائمة المركز المالي)';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.reports.balance-sheet';

    protected function data(): array
    {
        $section = function (string $type) {
            $rows = [];
            foreach (Account::query()->where('type', $type)->where('is_group', false)->orderBy('code')->get() as $a) {
                if ($v = $a->balance(null, $this->to)) {
                    $rows[] = ['code' => $a->code, 'name' => $a->name, 'value' => $v];
                }
            }

            return $rows;
        };

        $assets = $section('asset');
        $liabilities = $section('liability');
        $equity = $section('equity');
        $profit = IncomeStatement::compute('1900-01-01', $this->to)['net'];

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'profit' => $profit,
            'totalAssets' => array_sum(array_column($assets, 'value')),
            'totalLiabilities' => array_sum(array_column($liabilities, 'value')),
            'totalEquity' => array_sum(array_column($equity, 'value')) + $profit,
        ];
    }
}
