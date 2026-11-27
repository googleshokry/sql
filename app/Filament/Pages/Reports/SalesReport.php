<?php

namespace App\Filament\Pages\Reports;

use App\Models\SalesInvoiceItem;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/** تقرير المبيعات والأرباح */
class SalesReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'تقرير المبيعات والأرباح';

    protected static ?string $title = 'تقرير المبيعات والأرباح';

    protected static ?int $navigationSort = 7;

    protected static array $roles = ['accountant', 'manager', 'sales'];

    protected string $view = 'filament.reports.sales';

    #[Url]
    public string $groupBy = 'product';

    public const GROUPS = ['product' => 'حسب الصنف', 'customer' => 'حسب العميل', 'user' => 'حسب البائع', 'day' => 'حسب اليوم', 'category' => 'حسب التصنيف'];

    protected function data(): array
    {
        [$key, $label] = match ($this->groupBy) {
            'customer' => ['sales_invoices.customer_id', DB::raw("coalesce(customers.name, 'عميل نقدي') as label")],
            'user' => ['sales_invoices.created_by', DB::raw("coalesce(users.name, '-') as label")],
            'day' => [DB::raw('date(sales_invoices.date)'), DB::raw('date(sales_invoices.date) as label')],
            'category' => ['products.category_id', DB::raw("coalesce(categories.name, 'بدون تصنيف') as label")],
            default => ['sales_invoice_items.product_id', DB::raw('coalesce(products.name, sales_invoice_items.description) as label')],
        };

        $rows = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->leftJoin('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_invoices.created_by')
            ->whereNotNull('sales_invoices.posted_at')
            ->whereBetween('sales_invoices.date', [$this->from, $this->to])
            ->groupBy($key, 'label')
            ->select([
                $label,
                DB::raw('count(distinct sales_invoices.id) as invoices'),
                DB::raw('sum(sales_invoice_items.quantity) as qty'),
                // صافي الإيراد بعد توزيع خصم الفاتورة
                DB::raw('sum(sales_invoice_items.total * (1 - case when sales_invoices.subtotal > 0 then sales_invoices.discount_amount / sales_invoices.subtotal else 0 end)) as revenue'),
                DB::raw('sum(sales_invoice_items.quantity * sales_invoice_items.unit_cost) as cost'),
                DB::raw('sum(sales_invoice_items.tax_amount) as tax'),
            ])
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => $r->toArray() + ['profit' => (float) $r->revenue - (float) $r->cost]);

        return ['rows' => $rows, 'totals' => [
            'invoices' => $rows->sum('invoices'), 'qty' => $rows->sum('qty'), 'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('cost'), 'tax' => $rows->sum('tax'), 'profit' => $rows->sum('profit'),
        ]];
    }
}
