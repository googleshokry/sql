<?php

namespace App\Filament\Pages\Reports;

use App\Models\Customer;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Services\Accounting;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** كشف حساب عميل / مورد */
class PartyStatement extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'كشف حساب عميل / مورد';

    protected static ?string $title = 'كشف حساب عميل / مورد';

    protected static ?int $navigationSort = 1;

    protected static array $roles = ['accountant', 'manager', 'sales', 'purchases'];

    protected string $view = 'filament.reports.party-statement';

    #[Url]
    public string $party = 'customer';

    #[Url]
    public ?int $id = null;

    public function updatedParty(): void
    {
        $this->id = null;
    }

    protected function data(): array
    {
        $model = $this->party === 'supplier' ? Supplier::class : Customer::class;
        $options = $model::query()->orderBy('name')->pluck('name', 'id');
        $record = $this->id ? $model::find($this->id) : null;

        if (! $record) {
            return compact('options', 'record') + ['rows' => [], 'opening' => 0, 'closing' => 0, 'totals' => [0, 0]];
        }

        $account = Accounting::account($model::ACCOUNT_KEY)->id;
        $sign = $model === Customer::class ? 1 : -1; // العميل مدين بطبيعته، المورد دائن

        $base = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('account_id', $account)
            ->where('party_type', $record->getMorphClass())
            ->where('party_id', $record->id);

        $openingRow = (clone $base)->whereDate('journal_entries.date', '<', $this->from)
            ->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
        $opening = $sign * ((float) $openingRow->d - (float) $openingRow->c);

        $lines = (clone $base)
            ->whereDate('journal_entries.date', '>=', $this->from)
            ->whereDate('journal_entries.date', '<=', $this->to)
            ->orderBy('journal_entries.date')->orderBy('journal_entries.id')
            ->get(['journal_entries.date', 'journal_entries.number', 'journal_entries.description', 'debit', 'credit']);

        $balance = $opening;
        $rows = [];
        foreach ($lines as $l) {
            $balance += $sign * ((float) $l->debit - (float) $l->credit);
            $rows[] = ['date' => $l->date, 'number' => $l->number, 'description' => $l->description, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'balance' => $balance];
        }

        return compact('options', 'record', 'rows', 'opening') + [
            'closing' => $balance,
            'totals' => [$lines->sum('debit'), $lines->sum('credit')],
        ];
    }
}
