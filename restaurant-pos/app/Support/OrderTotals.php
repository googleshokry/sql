<?php

namespace App\Support;

use App\Enums\OrderType;

/** حساب إجماليات الطلب (مكان واحد يستخدمه الكاشير والطلبات) */
class OrderTotals
{
    public static function calculate(float $subtotal, float $discount, OrderType $type, float $deliveryFee = 0): array
    {
        $discount = round(min(max($discount, 0), $subtotal), 2);
        $afterDiscount = $subtotal - $discount;

        $service = $type === OrderType::DineIn
            ? round($afterDiscount * config('pos.service_rate', 0) / 100, 2)
            : 0.0;

        $tax = round(($afterDiscount + $service) * config('pos.tax_rate', 0) / 100, 2);
        $deliveryFee = $type === OrderType::Delivery ? round($deliveryFee, 2) : 0.0;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'service' => $service,
            'tax' => $tax,
            'delivery_fee' => $deliveryFee,
            'total' => round($afterDiscount + $service + $tax + $deliveryFee, 2),
        ];
    }
}
