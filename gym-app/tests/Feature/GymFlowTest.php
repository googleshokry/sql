<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['name' => 'a', 'email' => 'a@a.com', 'password' => 'x', 'role' => 'admin']));
    }

    public function test_member_gets_auto_code_and_cannot_enter_without_subscription(): void
    {
        $m = Member::create(['name' => 'Test', 'phone' => '010']);
        $this->assertNotEmpty($m->code);
        $this->assertFalse(AttendanceService::toggle($m)['ok']);
    }

    public function test_subscription_payment_and_session_based_checkin(): void
    {
        $plan = Plan::create(['name' => '2 sessions', 'duration_days' => 30, 'price' => 300, 'sessions_count' => 2]);
        $m = Member::create(['name' => 'Test', 'phone' => '010']);
        $s = SubscriptionService::create($m, ['plan_id' => $plan->id, 'discount' => 50, 'paid_amount' => 100]);

        $this->assertEquals(250, (float) $s->total);
        $this->assertEquals(150, $s->fresh()->remaining_amount);
        $this->assertEquals(today()->addDays(29)->toDateString(), $s->end_date->toDateString());

        $this->assertSame('in', AttendanceService::toggle($m)['action']);
        $this->assertSame('out', AttendanceService::toggle($m)['action']);
        $this->assertSame('in', AttendanceService::toggle($m)['action']);
        AttendanceService::toggle($m);

        $this->assertSame(Subscription::STATUS_EXPIRED, $s->fresh()->status);
        $this->assertFalse(AttendanceService::toggle($m)['ok']);
    }

    public function test_freeze_extends_end_date_and_blocks_checkin(): void
    {
        $plan = Plan::create(['name' => 'month', 'duration_days' => 30, 'price' => 400, 'freeze_days' => 10]);
        $m = Member::create(['name' => 'Test', 'phone' => '010']);
        $s = SubscriptionService::create($m, ['plan_id' => $plan->id]);
        $end = $s->end_date->copy();

        $s->freeze(5, today());
        $this->assertSame(Subscription::STATUS_FROZEN, $s->status);
        $this->assertTrue($s->end_date->equalTo($end->copy()->addDays(5)));
        $this->assertFalse(AttendanceService::toggle($m)['ok']);

        $s->unfreeze();
        $this->assertSame(Subscription::STATUS_ACTIVE, $s->status);
        $this->assertTrue($s->end_date->equalTo($end));
        $this->assertSame(0, $s->freeze_days_used);
    }

    public function test_renewal_starts_after_current_subscription(): void
    {
        $plan = Plan::create(['name' => 'month', 'duration_days' => 30, 'price' => 400]);
        $m = Member::create(['name' => 'Test', 'phone' => '010']);
        $s = SubscriptionService::create($m, ['plan_id' => $plan->id]);

        $this->assertEquals($s->end_date->copy()->addDay()->toDateString(), SubscriptionService::nextStartDate($m)->toDateString());
    }

    public function test_sale_updates_stock(): void
    {
        $p = Product::create(['name' => 'Water', 'sale_price' => 7, 'stock' => 10]);
        $sale = Sale::create(['discount' => 1]);
        $sale->items()->create(['product_id' => $p->id, 'quantity' => 3, 'unit_price' => 7]);
        $sale->recalculate();

        $this->assertEquals(20, (float) $sale->total);
        $this->assertSame(7, $p->fresh()->stock);
        $this->assertStringStartsWith('S', $sale->fresh()->invoice_number);
    }
}
