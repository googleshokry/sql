<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Models\JournalEntryLine;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** دفتر الأستاذ / كشف حساب */
class AccountLedger extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'دفتر الأستاذ';

    protected static ?string $title = 'دفتر الأستاذ (كشف حساب)';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.reports.account-ledger';

    #[Url]
    public ?int $account = null;

    protected function data(): array
    {
        $options = Account::query()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"]);
        $record = $this->account ? Account::find($this->account) : null;
        if (! $record) {
            return compact('options', 'record') + ['rows' => [], 'opening' => 0, 'closing' => 0, 'totals' => [0, 0]];
        }

        $sign = $record->isDebitNature() ? 1 : -1;
        $base = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('account_id', $record->descendantIds());

        $o = (clone $base)->whereDate('journal_entries.date', '<', $this->from)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
        $opening = $sign * ((float) $o->d - (float) $o->c);

        $lines = (clone $base)
            ->whereDate('journal_entries.date', '>=', $this->from)->whereDate('journal_entries.date', '<=', $this->to)
            ->orderBy('journal_entries.date')->orderBy('journal_entries.id')
            ->get(['journal_entries.id as entry_id', 'journal_entries.date', 'journal_entries.number', 'journal_entries.description', 'accounts.name as account_name', 'debit', 'credit', 'memo']);

        $balance = $opening;
        $rows = [];
        foreach ($lines as $l) {
            $balance += $sign * ((float) $l->debit - (float) $l->credit);
            $rows[] = $l->toArray() + ['balance' => $balance];
        }

        return compact('options', 'record', 'rows', 'opening') + ['closing' => $balance, 'totals' => [$lines->sum('debit'), $lines->sum('credit')]];
    }
}
