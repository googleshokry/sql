<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Filament\Pages\Pos;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['pos.tax_rate' => 0, 'pos.service_rate' => 10]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@pos.test')->first();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/pos', '/admin/orders', '/admin/categories', '/admin/products',
            '/admin/restaurant-tables', '/admin/customers', '/admin/drivers', '/admin/delivery-areas', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_cashier_cannot_access_admin_resources(): void
    {
        $this->actingAs(User::where('email', 'cashier@pos.test')->first());
        $this->get('/admin/pos')->assertOk();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/products')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_takeaway_paid_order(): void
    {
        $this->actingAs($this->admin());
        $p = Product::where('name', 'بيبسي')->first(); // 25

        Livewire::test(Pos::class)
            ->call('addToCart', $p->id)
            ->call('addToCart', $p->id)
            ->set('paidAmount', 100)
            ->call('checkout', true)
            ->assertSet('cart', []);

        $order = Order::with('items')->first();
        $this->assertEquals(50, (float) $order->total);
        $this->assertTrue($order->is_paid);
        $this->assertEquals(50, (float) $order->change_amount);
        $this->assertEquals(OrderStatus::Completed, $order->status);
        $this->assertEquals(2, $order->items->first()->quantity);

        $this->get(route('orders.receipt', $order))->assertOk()->assertSee($order->number);
        $this->get(route('orders.kitchen', $order))->assertOk()->assertSee('بيبسي');
    }

    public function test_dine_in_appends_to_open_table_order_then_pay(): void
    {
        $this->actingAs($this->admin());
        $table = RestaurantTable::first();
        $pizza = Product::where('name', 'بيتزا مارجريتا')->first(); // 130
        $tea = Product::where('name', 'شاي')->first(); // 15

        Livewire::test(Pos::class)
            ->call('setType', 'dine_in')
            ->call('selectTable', $table->id)
            ->call('addToCart', $pizza->id)
            ->call('checkout', false)
            ->call('selectTable', $table->id)
            ->call('addToCart', $tea->id)
            ->call('checkout', false);

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertEquals(145, (float) $order->subtotal);
        $this->assertEquals(14.5, (float) $order->service);
        $this->assertEquals(159.5, (float) $order->total);
        $this->assertEquals(TableStatus::Occupied, $table->fresh()->status);

        Livewire::test(ManageOrders::class)
            ->callTableAction('pay', $order, ['payment_method' => 'cash', 'paid_amount' => 200]);

        $order->refresh();
        $this->assertTrue($order->is_paid);
        $this->assertEquals(40.5, (float) $order->change_amount);
        $this->assertEquals(TableStatus::Available, $table->fresh()->status);
    }

    public function test_delivery_order_saves_customer_and_fee(): void
    {
        $this->actingAs($this->admin());
        $area = DeliveryArea::where('name', 'المعادي')->first(); // 35
        $p = Product::where('name', 'شاورما فراخ')->first(); // 65

        Livewire::test(Pos::class)
            ->call('setType', 'delivery')
            ->call('addToCart', $p->id)
            ->call('checkout', true)
            ->assertNotified('أدخل اسم وموبايل العميل')
            ->set('customerPhone', '01122334455')
            ->set('customerName', 'محمد')
            ->set('customerAddress', 'شارع 9')
            ->set('deliveryAreaId', $area->id)
            ->call('checkout', true);

        $order = Order::first();
        $this->assertEquals(100, (float) $order->total);
        $this->assertEquals(35, (float) $order->delivery_fee);
        $this->assertEquals('01122334455', $order->customer->phone);
        $this->assertEquals(OrderStatus::Preparing, $order->status);

        // العميل المسجل يتملى تلقائياً
        Livewire::test(Pos::class)
            ->call('setType', 'delivery')
            ->set('customerPhone', '01122334455')
            ->assertSet('customerName', 'محمد')
            ->assertSet('deliveryAreaId', $area->id);

        $this->assertSame(1, Customer::count());
    }
}
