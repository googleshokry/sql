<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Product;
use App\Support\OrderTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء الطلبات — يستخدمه الكاشير على الويب وتطبيقات الموبايل.
 */
class OrderService
{
    /**
     * @param  array{
     *   type: string,
     *   items: array<int, array{product_id:int, qty:int, notes?:?string}>,
     *   source?: string,
     *   user_id?: ?int,
     *   table_id?: ?int,
     *   customer_id?: ?int,
     *   customer?: array{phone?:?string, name?:?string, address?:?string, delivery_area_id?:?int},
     *   delivery_area_id?: ?int,
     *   address?: ?string,
     *   driver_id?: ?int,
     *   discount?: float|int|string|null,
     *   discount_type?: string,
     *   notes?: ?string,
     *   pay?: bool,
     *   payment_method?: ?string,
     *   paid_amount?: float|int|string|null,
     *   status?: OrderStatus,
     *   append_to_table?: bool,
     * }  $data
     */
    public function place(array $data): Order
    {
        $type = OrderType::from($data['type']);
        $customer = $data['customer'] ?? [];
        $areaId = $data['delivery_area_id'] ?? $customer['delivery_area_id'] ?? null;
        $address = $data['address'] ?? $customer['address'] ?? null;
        $pay = (bool) ($data['pay'] ?? false);

        $lines = $this->resolveItems($data['items'] ?? []);

        $error = match (true) {
            $lines === [] => 'السلة فارغة',
            $type === OrderType::DineIn && empty($data['table_id']) => 'اختر الطاولة',
            $type === OrderType::Delivery && empty($data['customer_id'])
                && (blank($customer['phone'] ?? null) || blank($customer['name'] ?? null)) => 'أدخل اسم وموبايل العميل',
            $type === OrderType::Delivery && blank($address) => 'أدخل عنوان التوصيل',
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['order' => $error]);
        }

        $subtotal = array_sum(array_column($lines, 'total'));
        $discount = (float) ($data['discount'] ?? 0);
        if (($data['discount_type'] ?? 'amount') === 'percent') {
            $discount = $subtotal * min($discount, 100) / 100;
        }
        $fee = $type === OrderType::Delivery && $areaId ? (float) (DeliveryArea::find($areaId)?->fee ?? 0) : 0;
        $totals = OrderTotals::calculate($subtotal, $discount, $type, $fee);

        return DB::transaction(function () use ($data, $type, $customer, $areaId, $address, $pay, $lines, $totals) {
            $customerId = $data['customer_id'] ?? null;
            if (! $customerId && filled($customer['phone'] ?? null)) {
                $customerId = Customer::updateOrCreate(
                    ['phone' => trim($customer['phone'])],
                    array_filter([
                        'name' => ($customer['name'] ?? null) ?: 'عميل',
                        'address' => $address ?: null,
                        'delivery_area_id' => $areaId,
                    ], fn ($v) => $v !== null),
                )->id;
            }

            $existing = $type === OrderType::DineIn && ($data['append_to_table'] ?? true)
                ? $this->openTableOrder((int) $data['table_id'])
                : null;

            $dueTotal = $totals['total'] + (float) ($existing?->total ?? 0);
            if ($pay && ($data['payment_method'] ?? 'cash') === PaymentMethod::Cash->value
                && filled($data['paid_amount'] ?? null) && (float) $data['paid_amount'] < $dueTotal) {
                throw ValidationException::withMessages(['paid_amount' => 'المبلغ المدفوع أقل من الإجمالي']);
            }

            $order = $existing ?? Order::create([
                'type' => $type,
                'status' => $data['status'] ?? OrderStatus::Preparing,
                'source' => $data['source'] ?? 'pos',
                'user_id' => $data['user_id'] ?? null,
                'restaurant_table_id' => $type === OrderType::DineIn ? $data['table_id'] : null,
                'customer_id' => $customerId,
                'delivery_area_id' => $type === OrderType::Delivery ? $areaId : null,
                'driver_id' => $type === OrderType::Delivery ? ($data['driver_id'] ?? null) : null,
                'delivery_address' => $type === OrderType::Delivery ? $address : null,
                'notes' => ($data['notes'] ?? null) ?: null,
                ...$totals,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            if ($existing) {
                $existing->update([
                    'discount' => (float) $existing->discount + $totals['discount'],
                    'notes' => trim($existing->notes.' '.($data['notes'] ?? '')) ?: null,
                ]);
                $existing->recalculate();
            }

            if ($type === OrderType::Delivery && ! empty($data['driver_id'])) {
                $order->update(['status' => OrderStatus::OutForDelivery]);
            }

            if ($pay) {
                $order->refresh()->markPaid(
                    PaymentMethod::from($data['payment_method'] ?? 'cash'),
                    filled($data['paid_amount'] ?? null) ? (float) $data['paid_amount'] : null,
                );
            }

            return $order->fresh(['items']);
        });
    }

    public function openTableOrder(int $tableId): ?Order
    {
        return Order::where('restaurant_table_id', $tableId)
            ->whereIn('status', OrderStatus::open())
            ->where('is_paid', false)
            ->latest('id')->first();
    }

    /** يقرأ الأسعار من قاعدة البيانات فقط */
    private function resolveItems(array $items): array
    {
        $items = array_values(array_filter($items, fn ($i) => ($i['qty'] ?? 0) > 0));
        $products = Product::where('is_active', true)
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()->keyBy('id');

        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'صنف غير متاح']);
            }
            $qty = (int) $item['qty'];
            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $qty,
                'total' => round((float) $product->price * $qty, 2),
                'notes' => ($item['notes'] ?? null) ?: null,
            ];
        }

        return $lines;
    }
}
