<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Models\JournalEntryLine;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** ميزان المراجعة */
class TrialBalance extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'ميزان المراجعة';

    protected static ?string $title = 'ميزان المراجعة';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.reports.trial-balance';

    protected function data(): array
    {
        $sums = fn ($q) => JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->tap($q)
            ->groupBy('account_id')
            ->selectRaw('account_id, sum(debit) d, sum(credit) c')
            ->get()->keyBy('account_id');

        $before = $sums(fn ($q) => $q->whereDate('journal_entries.date', '<', $this->from));
        $period = $sums(fn ($q) => $q->whereDate('journal_entries.date', '>=', $this->from)->whereDate('journal_entries.date', '<=', $this->to));

        $rows = [];
        $t = array_fill_keys(['od', 'oc', 'pd', 'pc', 'cd', 'cc'], 0);
        foreach (Account::query()->where('is_group', false)->orderBy('code')->get() as $a) {
            $open = (float) ($before[$a->id]->d ?? 0) - (float) ($before[$a->id]->c ?? 0);
            $pd = (float) ($period[$a->id]->d ?? 0);
            $pc = (float) ($period[$a->id]->c ?? 0);
            $close = $open + $pd - $pc;
            if (abs($open) < 0.005 && $pd == 0 && $pc == 0) {
                continue;
            }
            $row = [
                'code' => $a->code, 'name' => $a->name, 'id' => $a->id,
                'od' => max($open, 0), 'oc' => max(-$open, 0),
                'pd' => $pd, 'pc' => $pc,
                'cd' => max($close, 0), 'cc' => max(-$close, 0),
            ];
            foreach ($t as $k => $_) {
                $t[$k] += $row[$k];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'totals' => $t];
    }
}
