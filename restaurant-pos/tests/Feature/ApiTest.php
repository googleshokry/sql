<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['pos.tax_rate' => 0, 'pos.service_rate' => 0]);
    }

    /** تبديل المستخدم بين الطلبات (Laravel يحتفظ بالمستخدم داخل الاختبار) */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function staffToken(string $email): string
    {
        return $this->postJson('/api/staff/login', ['email' => $email, 'password' => 'password'])
            ->assertOk()->json('token');
    }

    public function test_public_menu_and_settings(): void
    {
        $this->getJson('/api/menu')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'products' => [['id', 'name', 'price']]]]]);
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('currency', 'ج.م');
        $this->getJson('/api/delivery-areas')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_login_rejects_bad_password_and_driver_cannot_use_web_panel(): void
    {
        $this->postJson('/api/staff/login', ['email' => 'admin@pos.test', 'password' => 'x'])->assertUnprocessable();
        $this->postJson('/api/staff/login', ['email' => 'driver@pos.test', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'driver');
        $this->actingAs(\App\Models\User::firstWhere('email', 'driver@pos.test'))->get('/admin')->assertForbidden();
    }

    public function test_cashier_app_creates_and_pays_dine_in_order(): void
    {
        $token = $this->staffToken('cashier@pos.test');
        $table = RestaurantTable::first();
        $p = Product::where('name', 'كنافة')->first(); // 60

        $id = $this->as($token)->postJson('/api/pos/orders', [
            'type' => 'dine_in', 'table_id' => $table->id,
            'items' => [['product_id' => $p->id, 'qty' => 2, 'price' => 1]], // السعر من العميل يتم تجاهله
        ])->assertCreated()->assertJsonPath('data.total', 120)->assertJsonPath('data.source', 'staff_app')->json('data.id');

        $this->as($token)->getJson('/api/pos/tables')->assertOk()
            ->assertJsonPath('data.0.status', 'occupied')->assertJsonPath('data.0.open_order.total', 120);

        $this->as($token)->postJson("/api/orders/$id/pay", ['payment_method' => 'cash', 'paid_amount' => 100])->assertUnprocessable();
        $this->as($token)->postJson("/api/orders/$id/pay", ['payment_method' => 'cash', 'paid_amount' => 200])
            ->assertOk()->assertJsonPath('data.change_amount', 80)->assertJsonPath('data.status', 'completed');

        $this->assertEquals('available', $table->fresh()->status->value);
        // الكاشير لا يرى لوحة المالك
        $this->as($token)->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_customer_app_order_flow_to_driver_delivery_and_owner_dashboard(): void
    {
        $area = DeliveryArea::first();
        $p = Product::where('name', 'بيتزا سجق')->first(); // 165

        // العميل يسجل ويطلب
        $customerToken = $this->postJson('/api/customer/register', [
            'name' => 'سارة', 'phone' => '01234567890', 'password' => 'secret12',
        ])->assertCreated()->json('token');

        $this->as($customerToken)->getJson('/api/orders')->assertForbidden();

        $orderId = $this->as($customerToken)->postJson('/api/customer/orders', [
            'type' => 'delivery', 'delivery_area_id' => $area->id, 'address' => 'شارع النصر',
            'items' => [['product_id' => $p->id, 'qty' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 165 + (int) $area->fee)
            ->json('data.id');

        // الكاشير يشوف الطلب ويأكده ويعين الطيار
        $cashier = $this->staffToken('cashier@pos.test');
        $this->as($cashier)->getJson('/api/orders?filter=app')->assertOk()->assertJsonPath('data.0.id', $orderId);
        $this->as($cashier)->postJson("/api/orders/$orderId/status", ['status' => 'preparing'])->assertOk();
        $this->as($cashier)->postJson("/api/orders/$orderId/status", ['status' => 'cancelled'])->assertForbidden();
        $driver = Driver::where('name', 'أحمد')->first();
        $this->as($cashier)->postJson("/api/orders/$orderId/driver", ['driver_id' => $driver->id])
            ->assertOk()->assertJsonPath('data.status', 'out_for_delivery');

        // العميل لا يقدر يلغي بعد التحضير
        $this->as($customerToken)->postJson("/api/customer/orders/$orderId/cancel")->assertUnprocessable();
        $this->as($customerToken)->getJson("/api/customer/orders/$orderId")->assertOk()
            ->assertJsonPath('data.driver.name', 'أحمد');

        // الطيار يسلم ويحصل
        $driverToken = $this->staffToken('driver@pos.test');
        $this->as($driverToken)->getJson('/api/driver/orders')->assertOk()->assertJsonPath('data.0.id', $orderId);
        $this->as($driverToken)->postJson("/api/driver/orders/$orderId/delivered")
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.is_paid', true);
        $this->as($driverToken)->getJson('/api/driver/orders')
            ->assertJsonPath('summary.cash_to_hand_over', 165 + (int) $area->fee);

        // المالك يشوف التقرير
        $admin = $this->staffToken('admin@pos.test');
        $this->as($admin)->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('sales', 165 + (int) $area->fee)
            ->assertJsonPath('top_products.0.name', 'بيتزا سجق')
            ->assertJsonPath('drivers.0.name', 'أحمد');

        // رابط الفاتورة الموقّع يفتح بدون تسجيل دخول
        $url = $this->as($admin)->getJson("/api/orders/$orderId")->json('data.receipt_url');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertSee(Order::find($orderId)->number);
    }
}
