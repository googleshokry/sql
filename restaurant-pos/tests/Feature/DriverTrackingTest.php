<?php

namespace Tests\Feature;

use App\Models\DeliveryArea;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->driver = Driver::firstWhere('name', 'أحمد');
    }

    private array $tokens = [];

    private function as(string $email): static
    {
        $this->app['auth']->forgetGuards();
        $this->tokens[$email] ??= $this->withoutToken()->postJson('/api/staff/login', ['email' => $email, 'password' => 'password'])->json('token');
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokens[$email]);
    }

    private function customerOrder(): array
    {
        $token = $this->postJson('/api/customer/register', ['name' => 'منى', 'phone' => '01555555555', 'password' => 'secret12'])->json('token');
        $this->app['auth']->forgetGuards();
        $id = $this->withToken($token)->postJson('/api/customer/orders', [
            'type' => 'delivery', 'delivery_area_id' => DeliveryArea::first()->id, 'address' => 'شارع 9',
            'lat' => 30.05, 'lng' => 31.24,
            'items' => [['product_id' => Product::first()->id, 'qty' => 1]],
        ])->assertCreated()->assertJsonPath('data.delivery_location.lat', 30.05)->json('data.id');

        return [$token, $id];
    }

    public function test_driver_shift_location_and_delivery_stages(): void
    {
        [$customerToken, $orderId] = $this->customerOrder();
        $this->as('cashier@pos.test')->postJson("/api/orders/$orderId/driver", ['driver_id' => $this->driver->id])
            ->assertOk()->assertJsonPath('data.delivery_stage', 'assigned');

        // الطيار يبدأ الشيفت ويبعت موقعه
        $this->as('driver@pos.test')->postJson('/api/driver/shift', ['online' => true])->assertOk()->assertJsonPath('is_online', true);
        $this->as('driver@pos.test')->postJson('/api/driver/location', ['lat' => 30.0444, 'lng' => 31.2357, 'accuracy' => 8])
            ->assertOk()->assertJsonPath('location.live', true);
        $this->as('driver@pos.test')->postJson('/api/driver/location', ['lat' => 200, 'lng' => 31])->assertUnprocessable();

        // المراحل
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/pickup")->assertOk()
            ->assertJsonPath('data.delivery_stage', 'picked_up');

        // العميل يشوف موقع الطيار والطلب في الطريق
        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->getJson("/api/customer/orders/$orderId")->assertOk()
            ->assertJsonPath('data.driver_location.lat', 30.0444)
            ->assertJsonPath('data.delivery_stage_label', fn ($v) => is_string($v));

        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/arrived")->assertOk()
            ->assertJsonPath('data.delivery_stage', 'arrived');
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/delivered")->assertOk()
            ->assertJsonPath('data.delivery_stage', 'delivered')->assertJsonPath('data.is_paid', true);

        $order = Order::find($orderId);
        $this->assertNotNull($order->picked_up_at);
        $this->assertNotNull($order->arrived_at);
        $this->assertNotNull($order->delivered_at);

        // الكاشير يشوف الطيار على الخريطة
        $this->as('cashier@pos.test')->getJson('/api/drivers/locations')->assertOk()
            ->assertJsonFragment(['name' => 'أحمد', 'live' => true]);
        $this->as('cashier@pos.test')->getJson("/api/drivers/{$this->driver->id}/trail")->assertOk()->assertJsonCount(1, 'data');

        // إنهاء الشيفت
        $this->as('driver@pos.test')->postJson('/api/driver/shift', ['online' => false])->assertJsonPath('is_online', false);
        $this->assertFalse($this->driver->fresh()->isLive());
    }

    public function test_failed_delivery_returns_order_to_restaurant(): void
    {
        [, $orderId] = $this->customerOrder();
        $this->as('cashier@pos.test')->postJson("/api/orders/$orderId/driver", ['driver_id' => $this->driver->id]);
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/pickup")->assertOk();
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/failed", [])->assertUnprocessable();
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/failed", ['reason' => 'العميل مش بيرد'])->assertNoContent();

        $order = Order::find($orderId);
        $this->assertNull($order->driver_id);
        $this->assertSame('ready', $order->status->value);
        $this->assertStringContainsString('العميل مش بيرد', $order->driver_note);

        // الطيار ما يقدرش يلمس طلب مش بتاعه
        $this->as('driver@pos.test')->postJson("/api/driver/orders/$orderId/delivered")->assertNotFound();
    }

    public function test_drivers_map_page_renders_for_staff_and_api_is_staff_only(): void
    {
        $this->driver->recordLocation(30.06, 31.25, 5);
        $this->actingAs(User::firstWhere('email', 'cashier@pos.test'));
        $this->get('/admin/drivers-map')->assertOk()->assertSee('vendor/leaflet/leaflet.js', false)->assertSee('أحمد');

        $this->as('driver@pos.test')->getJson('/api/drivers/locations')->assertForbidden();
    }
}
