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
use App\Support\OrderTotals;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use UnitEnum;

class Pos extends Page
{
    protected string $view = 'filament.pages.pos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?string $navigationLabel = 'شاشة الكاشير';

    protected static ?string $title = 'شاشة الكاشير';

    protected static ?int $navigationSort = 1;

    protected Width|string|null $maxContentWidth = Width::Full;

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
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
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

        return Order::where('restaurant_table_id', $this->tableId)
            ->whereIn('status', OrderStatus::open())
            ->where('is_paid', false)
            ->latest('id')->first();
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
            'name' => $product->name,
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
            Notification::make()->success()->title('عميل مسجل: '.$customer->name)->send();
        } else {
            $this->customerId = null;
        }
    }

    // ---- حفظ الطلب ----
    public function checkout(bool $pay = true): void
    {
        $type = OrderType::from($this->orderType);
        $totals = $this->totals;
        $dueTotal = $totals['total'] + (float) ($this->openTableOrder?->total ?? 0);

        $error = match (true) {
            empty($this->cart) => 'السلة فارغة',
            $type === OrderType::DineIn && ! $this->tableId => 'اختر الطاولة',
            $type === OrderType::Delivery && (blank($this->customerPhone) || blank($this->customerName)) => 'أدخل اسم وموبايل العميل',
            $type === OrderType::Delivery && blank($this->customerAddress) => 'أدخل عنوان التوصيل',
            $pay && $this->paymentMethod === PaymentMethod::Cash->value && filled($this->paidAmount)
                && (float) $this->paidAmount < $dueTotal => 'المبلغ المدفوع أقل من الإجمالي',
            default => null,
        };

        if ($error) {
            Notification::make()->danger()->title($error)->send();

            return;
        }

        $order = DB::transaction(function () use ($type, $totals, $pay) {
            $customerId = null;
            if (filled($this->customerPhone)) {
                $customer = Customer::updateOrCreate(
                    ['phone' => trim($this->customerPhone)],
                    array_filter([
                        'name' => $this->customerName ?: 'عميل',
                        'address' => $this->customerAddress ?: null,
                        'delivery_area_id' => $this->deliveryAreaId,
                    ], fn ($v) => $v !== null),
                );
                $customerId = $customer->id;
            }

            // طلب صالة مفتوح على نفس الطاولة؟ نضيف عليه
            $existing = $type === OrderType::DineIn
                ? Order::where('restaurant_table_id', $this->tableId)
                    ->whereIn('status', OrderStatus::open())
                    ->where('is_paid', false)
                    ->latest('id')->first()
                : null;

            $order = $existing ?? Order::create([
                'type' => $type,
                'status' => OrderStatus::Preparing,
                'user_id' => auth()->id(),
                'restaurant_table_id' => $type === OrderType::DineIn ? $this->tableId : null,
                'customer_id' => $customerId,
                'delivery_area_id' => $type === OrderType::Delivery ? $this->deliveryAreaId : null,
                'driver_id' => $type === OrderType::Delivery ? $this->driverId : null,
                'delivery_address' => $type === OrderType::Delivery ? $this->customerAddress : null,
                'notes' => $this->notes ?: null,
                ...$totals,
            ]);

            foreach ($this->cart as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['qty'],
                    'total' => round($item['price'] * $item['qty'], 2),
                    'notes' => $item['notes'] ?: null,
                ]);
            }

            if ($existing) {
                $existing->update([
                    'discount' => (float) $existing->discount + $totals['discount'],
                    'notes' => trim($existing->notes.' '.$this->notes) ?: null,
                ]);
                $existing->recalculate();
            }

            if ($type === OrderType::Delivery && $this->driverId) {
                $order->update(['status' => OrderStatus::OutForDelivery]);
            }

            if ($pay) {
                $order->refresh();
                $order->markPaid(
                    PaymentMethod::from($this->paymentMethod),
                    filled($this->paidAmount) ? (float) $this->paidAmount : null,
                );
            }

            return $order->fresh();
        });

        $msg = $pay ? 'تم حفظ الطلب '.$order->number : 'تم إرسال الطلب '.$order->number.' (غير مدفوع)';
        if ($pay && $order->change_amount > 0) {
            $msg .= ' | الباقي للعميل: '.number_format($order->change_amount, 2);
        }
        Notification::make()->success()->title($msg)->persistent()->send();

        $js = "window.open('".route('orders.receipt', $order)."', '_blank');";
        if ($this->printKitchen) {
            $js .= "window.open('".route('orders.kitchen', $order)."', '_blank');";
        }
        $this->js($js);

        $type = $this->orderType;
        $this->clearCart();
        $this->orderType = $type;
        unset($this->tables, $this->openTableOrder);
    }
}
