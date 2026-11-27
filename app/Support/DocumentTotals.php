<?php

namespace App\Support;

class DocumentTotals
{
    public static function line(array $item): array
    {
        $qty = (float) ($item['quantity'] ?? 0);
        $price = (float) ($item['unit_price'] ?? 0);
        $disc = (float) ($item['discount_percent'] ?? 0);
        $rate = (float) ($item['tax_rate'] ?? 0);

        $total = round($qty * $price * (1 - $disc / 100), 2);

        return [
            'total' => $total,
            'tax_amount' => round($total * $rate / 100, 2),
        ];
    }

    /**
     * @return array{subtotal: float, discount_amount: float, tax_amount: float, shipping: float, total: float}
     */
    public static function calculate(array $items, ?string $discountType, float $discountValue, float $shipping = 0): array
    {
        $subtotal = 0;
        $tax = 0;
        foreach ($items as $item) {
            $line = static::line((array) $item);
            $subtotal += $line['total'];
            $tax += $line['tax_amount'];
        }

        $discount = $discountType === 'percent' ? $subtotal * $discountValue / 100 : $discountValue;
        $discount = min(max($discount, 0), $subtotal);

        // الخصم على مستوى الفاتورة يقلّل وعاء الضريبة بنفس النسبة
        if ($subtotal > 0) {
            $tax = $tax * (1 - $discount / $subtotal);
        }

        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);
        $tax = round($tax, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping' => round($shipping, 2),
            'total' => round($subtotal - $discount + $tax + $shipping, 2),
        ];
    }
}
