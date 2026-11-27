<?php

namespace Tests\Unit;

use App\Enums\OrderType;
use App\Support\OrderTotals;
use Tests\TestCase;

class OrderTotalsTest extends TestCase
{
    public function test_service_only_on_dine_in_and_delivery_fee_only_on_delivery(): void
    {
        config(['pos.service_rate' => 10, 'pos.tax_rate' => 14]);

        $dineIn = OrderTotals::calculate(100, 0, OrderType::DineIn, 30);
        $this->assertSame(10.0, $dineIn['service']);
        $this->assertSame(15.4, $dineIn['tax']);          // (100 + 10) * 14%
        $this->assertSame(0.0, $dineIn['delivery_fee']);
        $this->assertSame(125.4, $dineIn['total']);

        $delivery = OrderTotals::calculate(100, 20, OrderType::Delivery, 30);
        $this->assertSame(0.0, $delivery['service']);
        $this->assertSame(11.2, $delivery['tax']);        // (100 - 20) * 14%
        $this->assertSame(121.2, $delivery['total']);
    }

    public function test_discount_is_clamped_to_subtotal(): void
    {
        config(['pos.service_rate' => 0, 'pos.tax_rate' => 0]);

        $t = OrderTotals::calculate(50, 80, OrderType::Takeaway);
        $this->assertSame(50.0, $t['discount']);
        $this->assertSame(0.0, $t['total']);
    }
}
