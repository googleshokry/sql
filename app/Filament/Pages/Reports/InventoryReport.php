<?php

namespace App\Filament\Pages\Reports;

use App\Models\Product;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** تقرير جرد وتقييم المخزون */
class InventoryReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'تقييم المخزون';

    protected static ?string $title = 'تقرير أرصدة وتقييم المخزون';

    protected static ?int $navigationSort = 8;

    protected static array $roles = ['accountant', 'manager', 'storekeeper'];

    protected string $view = 'filament.reports.inventory';

    #[Url]
    public ?int $warehouse = null;

    #[Url]
    public bool $lowOnly = false;

    protected function data(): array
    {
        $warehouses = Warehouse::query()->pluck('name', 'id');
        $rows = Product::query()
            ->where('track_stock', true)
            ->with('category', 'unit')
            ->withSum(['stocks as qty' => fn ($q) => $q->when($this->warehouse, fn ($q) => $q->where('warehouse_id', $this->warehouse))], 'quantity')
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => ! $this->lowOnly || (float) $p->qty <= (float) $p->min_stock)
            ->map(fn (Product $p) => [
                'sku' => $p->sku, 'name' => $p->name, 'category' => $p->category?->name, 'unit' => $p->unit?->name,
                'qty' => (float) $p->qty, 'min' => (float) $p->min_stock, 'cost' => (float) $p->cost_price,
                'value' => (float) $p->qty * (float) $p->cost_price, 'sale_value' => (float) $p->qty * (float) $p->sale_price,
            ]);

        return ['warehouses' => $warehouses, 'rows' => $rows, 'totals' => ['value' => $rows->sum('value'), 'sale_value' => $rows->sum('sale_value'), 'qty' => $rows->sum('qty')]];
    }
}
