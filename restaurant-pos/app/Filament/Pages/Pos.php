<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use App\Support\OrderTotals;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use UnitEnum;

class Pos extends Page
{
    protected string $view = 'filament.pages.pos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Sales;

    protected static ?int $navigationSort = 1;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getNavigationLabel(): string
    {
        return __('شاشة الكاشير');
    }

    public function getTitle(): string
    {
        return __('شاشة الكاشير');
    }

    // ---- حالة الشاشة ----
    public ?int $categoryId = null;

    public string $search = '';

    /** @var array<int, array{product_id:int,name:string,price:float,qty:int,notes:string}> */
    public array $cart = [];

    public string $orderType = 'takeaway';

    public ?int $tableId = null;

    public ?int $customerId = null;

    public string $customerPhone = '';

    public string $customerName = '';

    public string $customerAddress = '';

    public ?int $deliveryAreaId = null;

    public ?int $driverId = null;

    public $discount = 0;

    public string $discountType = 'amount';

    public string $paymentMethod = 'cash';

    public $paidAmount = null;

    public string $notes = '';

    public bool $printKitchen = true;

    // ---- بيانات ----
    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)->orderBy('sort')->get();
    }

    #[Computed]
    public function products()
    {
        return Product::where('is_active', true)
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('name_en', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function tables()
    {
        return RestaurantTable::orderBy('id')->get();
    }

    #[Computed]
    public function areas()
    {
        return DeliveryArea::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function drivers()
    {
        return Driver::where('is_active', true)->orderBy('name')->get();
    }

    /** طلب مفتوح غير مدفوع على الطاولة المختارة (سيتم الإضافة عليه) */
    #[Computed]
    public function openTableOrder(): ?Order
    {
        if ($this->orderType !== OrderType::DineIn->value || ! $this->tableId) {
            return null;
        }

        return app(OrderService::class)->openTableOrder($this->tableId);
    }

    #[Computed]
    public function totals(): array
    {
        $subtotal = collect($this->cart)->sum(fn ($i) => $i['price'] * $i['qty']);
        $discount = (float) ($this->discount ?: 0);
        if ($this->discountType === 'percent') {
            $discount = $subtotal * min($discount, 100) / 100;
        }
        $fee = $this->orderType === OrderType::Delivery->value && $this->deliveryAreaId
            ? (float) (DeliveryArea::find($this->deliveryAreaId)?->fee ?? 0)
            : 0;

        $totals = OrderTotals::calculate($subtotal, $discount, OrderType::from($this->orderType), $fee);
        $paid = (float) ($this->paidAmount ?: 0);
        $totals['change'] = $paid > 0 ? max(0, round($paid - $totals['total'], 2)) : 0;

        return $totals;
    }

    // ---- السلة ----
    public function addToCart(int $productId): void
    {
        foreach ($this->cart as $i => $item) {
            if ($item['product_id'] === $productId && $item['notes'] === '') {
                $this->cart[$i]['qty']++;

                return;
            }
        }

        $product = Product::findOrFail($productId);
        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->localized_name,
            'price' => (float) $product->price,
            'qty' => 1,
            'notes' => '',
        ];
    }

    public function increment(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['qty']++;
        }
    }

    public function decrement(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if (--$this->cart[$index]['qty'] < 1) {
            $this->removeItem($index);
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->reset(['cart', 'discount', 'discountType', 'paidAmount', 'notes', 'tableId',
            'customerId', 'customerPhone', 'customerName', 'customerAddress', 'deliveryAreaId', 'driverId']);
    }

    public function setType(string $type): void
    {
        $this->orderType = OrderType::from($type)->value;
    }

    public function selectTable(int $id): void
    {
        $this->tableId = $this->tableId === $id ? null : $id;
    }

    public function quickPay(float $amount): void
    {
        $this->paidAmount = $amount;
    }

    /** البحث عن العميل برقم الموبايل */
    public function updatedCustomerPhone(): void
    {
        $customer = Customer::where('phone', trim($this->customerPhone))
            ->orWhere('phone2', trim($this->customerPhone))
            ->first();

        if ($customer) {
            $this->customerId = $customer->id;
            $this->customerName = $customer->name;
            $this->customerAddress = (string) $customer->address;
            $this->deliveryAreaId = $customer->delivery_area_id;
            Notification::make()->success()->title(__('عميل مسجل: ').$customer->name)->send();
        } else {
            $this->customerId = null;
        }
    }

    // ---- حفظ الطلب ----
    public function checkout(bool $pay = true): void
    {
        try {
            $order = app(OrderService::class)->place([
                'type' => $this->orderType,
                'source' => 'pos',
                'user_id' => auth()->id(),
                'table_id' => $this->tableId,
                'customer' => [
                    'phone' => $this->customerPhone,
                    'name' => $this->customerName,
                    'address' => $this->customerAddress,
                    'delivery_area_id' => $this->deliveryAreaId,
                ],
                'delivery_area_id' => $this->deliveryAreaId,
                'address' => $this->customerAddress,
                'driver_id' => $this->driverId,
                'items' => array_map(fn ($i) => [
                    'product_id' => $i['product_id'], 'qty' => $i['qty'], 'notes' => $i['notes'],
                ], $this->cart),
                'discount' => $this->discount ?: 0,
                'discount_type' => $this->discountType,
                'notes' => $this->notes,
                'pay' => $pay,
                'payment_method' => $this->paymentMethod,
                'paid_amount' => $this->paidAmount,
            ]);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }

        $msg = $pay ? __('تم حفظ الطلب ').$order->number : __('تم إرسال الطلب ').$order->number.__(' (غير مدفوع)');
        if ($pay && $order->change_amount > 0) {
            $msg .= __(' | الباقي للعميل: ').number_format($order->change_amount, 2);
        }
        Notification::make()->success()->title($msg)->persistent()->send();

        $js = "window.open('".route('orders.receipt', ['order' => $order])."', '_blank');";
        if ($this->printKitchen) {
            $js .= "window.open('".route('orders.kitchen', ['order' => $order])."', '_blank');";
        }
        $this->js($js);

        $type = $this->orderType;
        $this->clearCart();
        $this->orderType = $type;
        unset($this->tables, $this->openTableOrder);
    }
}
