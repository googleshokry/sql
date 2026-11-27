<?php

namespace App\Filament\Pages\Reports;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** أعمار الديون (الذمم المدينة والدائنة) */
class AgingReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'أعمار الديون';

    protected static ?string $title = 'تقرير أعمار الديون';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.reports.aging';

    #[Url]
    public string $party = 'customer';

    protected function data(): array
    {
        $model = $this->party === 'supplier' ? PurchaseInvoice::class : SalesInvoice::class;
        $relation = $this->party === 'supplier' ? 'supplier' : 'customer';

        $buckets = ['0-30', '31-60', '61-90', '90+'];
        $rows = [];
        foreach ($model::query()->with($relation)->whereNotNull('posted_at')->where('payment_status', '!=', 'paid')->get() as $inv) {
            $remaining = $inv->remainingAmount();
            if ($remaining <= 0) {
                continue;
            }
            $days = ($inv->due_date ? Carbon::parse($inv->due_date) : $inv->date)->diffInDays(now(), false);
            $bucket = match (true) {
                $days <= 30 => '0-30', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+'
            };
            $name = $inv->{$relation}?->name ?? __('نقدي');
            $rows[$name] ??= array_fill_keys($buckets, 0) + ['total' => 0, 'count' => 0];
            $rows[$name][$bucket] += $remaining;
            $rows[$name]['total'] += $remaining;
            $rows[$name]['count']++;
        }
        uasort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = array_fill_keys([...$buckets, 'total'], 0);
        foreach ($rows as $r) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $r[$k];
            }
        }

        return compact('rows', 'buckets', 'totals');
    }
}
