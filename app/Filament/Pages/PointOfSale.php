<?php

namespace App\Filament\Pages;

use App\Exceptions\BusinessException;
use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Treasury;
use App\Models\Warehouse;
use App\Services\SalesCheckout;
use App\Support\DocumentTotals;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** نقطة البيع POS */
class PointOfSale extends Page
{
    use HasRoleAccess;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'نقطة البيع (POS)';

    protected static ?string $title = 'نقطة البيع';

    protected static array $roles = ['sales', 'manager'];

    protected string $view = 'filament.pages.pos';

    public string $search = '';

    public ?int $categoryId = null;

    public ?int $customerId = null;

    public ?int $warehouseId = null;

    public ?int $treasuryId = null;

    public string $method = 'cash';

    /** @var array<int, array{product_id:int,name:string,quantity:float,unit_price:float,discount_percent:float,tax_rate:float}> */
    public array $cart = [];

    public float $discount = 0;

    public float $paid = 0;

    public ?int $lastInvoiceId = null;

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->where('is_default', true)->value('id') ?? Warehouse::query()->value('id');
        $this->treasuryId = Treasury::query()->where('is_default', true)->value('id') ?? Treasury::query()->value('id');
        $this->customerId = Customer::query()->where('name', 'عميل نقدي')->value('id');
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    /** إضافة بالباركود (Enter في حقل البحث) */
    public function scan(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $product = Product::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))->first();
        if ($product) {
            $this->addProduct($product->id);
            $this->search = '';
        }
    }

    public function addProduct(int $id): void
    {
        $product = Product::find($id);
        if (! $product) {
            return;
        }
        $key = collect($this->cart)->search(fn ($l) => $l['product_id'] === $id);
        if ($key !== false) {
            $this->cart[$key]['quantity']++;

            return;
        }
        $priceList = $this->customerId ? Customer::find($this->customerId)?->price_list : null;
        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->priceFor($priceList),
            'discount_percent' => 0,
            'tax_rate' => (float) $product->tax_rate,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discount = 0;
        $this->paid = 0;
    }

    public function totals(): array
    {
        return DocumentTotals::calculate($this->cart, 'fixed', (float) $this->discount);
    }

    public function payExact(): void
    {
        $this->paid = $this->totals()['total'];
    }

    public function checkout(): void
    {
        $totals = $this->totals();

        try {
            $invoice = SalesCheckout::checkout(
                lines: $this->cart,
                customerId: $this->customerId,
                warehouseId: (int) $this->warehouseId,
                treasuryId: $this->treasuryId,
                method: $this->method,
                discount: (float) $this->discount,
                paid: (float) $this->paid,
            );
        } catch (BusinessException $e) {
            Notification::make()->title(__('تعذّر إتمام البيع'))->body($e->getMessage())->danger()->send();

            return;
        }

        $change = (float) $this->paid - (float) $totals['total'];
        $this->lastInvoiceId = $invoice->id;
        $this->clearCart();
        Notification::make()
            ->title(__('تم البيع - فاتورة').' '.$invoice->number)
            ->body($change > 0 ? __('الباقي للعميل:').' '.number_format($change, 2) : null)
            ->success()->send();
        $this->js('window.open('.json_encode(route('print.document', ['sales-invoice', $invoice->id, 'format' => 'thermal'])).', "_blank", "width=420,height=700")');
    }

    protected function getViewData(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('barcode', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")))
            ->withSum(['stocks as qty' => fn ($q) => $q->where('warehouse_id', $this->warehouseId)], 'quantity')
            ->orderBy('name')
            ->limit(60)
            ->get();

        return [
            'products' => $products,
            'categories' => Category::query()->pluck('name', 'id'),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::query()->where('is_active', true)->pluck('name', 'id'),
            'treasuries' => Treasury::query()->where('is_active', true)->pluck('name', 'id'),
            'totals' => $this->totals(),
            'currency' => __(Setting::get('currency')),
        ];
    }
}
