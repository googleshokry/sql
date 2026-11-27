<?php

namespace App\Filament\Pages\Reports;

use App\Models\Expense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** الإقرار الضريبي - ضريبة القيمة المضافة */
class VatReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'الإقرار الضريبي (VAT)';

    protected static ?string $title = 'إقرار ضريبة القيمة المضافة';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.reports.vat';

    public function mount(): void
    {
        $this->from ??= now()->firstOfQuarter()->toDateString();
        $this->to ??= now()->lastOfQuarter()->toDateString();
    }

    protected function data(): array
    {
        $sum = fn (string $model) => $model::query()->whereNotNull('posted_at')
            ->whereBetween('date', [$this->from, $this->to])
            ->selectRaw('coalesce(sum(subtotal - discount_amount),0) base, coalesce(sum(tax_amount),0) vat')->first();

        $sales = $sum(SalesInvoice::class);
        $salesReturns = $sum(SalesReturn::class);
        $purchases = $sum(PurchaseInvoice::class);
        $purchaseReturns = $sum(PurchaseReturn::class);
        $expenses = Expense::query()->whereBetween('date', [$this->from, $this->to])->where('tax_amount', '>', 0)
            ->selectRaw('coalesce(sum(amount),0) base, coalesce(sum(tax_amount),0) vat')->first();

        $output = (float) $sales->vat - (float) $salesReturns->vat;
        $input = (float) $purchases->vat - (float) $purchaseReturns->vat + (float) $expenses->vat;

        return compact('sales', 'salesReturns', 'purchases', 'purchaseReturns', 'expenses', 'output', 'input') + ['net' => $output - $input];
    }
}
