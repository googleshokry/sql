<?php

namespace Tests\Feature;

use App\Filament\Platform\Resources\Payments\Pages\ManagePayments;
use App\Filament\Platform\Resources\Tenants\Pages\ManageTenants;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Product;
use App\Models\User;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantRegistrar;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SaasTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_and_signup_provision_a_new_restaurant_database(): void
    {
        $this->seed(PlanSeeder::class);
        $this->get('/')->assertOk()->assertSee('الباقات')->assertSee('احترافي');
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'كشري التحرير', 'slug' => 'Admin', 'owner_name' => 'محمد', 'owner_email' => 'm@x.com',
            'owner_phone' => '0100', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'plan_id' => Plan::first()->id,
        ])->assertSessionHasErrors('slug'); // كود محجوز

        $this->post('/register', [
            'name' => 'كشري التحرير', 'slug' => 'koshary-tahrir', 'owner_name' => 'محمد', 'owner_email' => 'm@x.com',
            'owner_phone' => '0100', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'plan_id' => Plan::first()->id, 'demo' => '1',
        ])->assertRedirect('/app/koshary-tahrir/login');

        $tenant = Tenant::firstWhere('slug', 'koshary-tahrir');
        $this->assertSame('trial', $tenant->accessState());
        $this->assertSame(14, $tenant->daysLeft());

        // صاحب المطعم اتعمل في قاعدة بيانات المطعم ومعاه منيو تجريبي
        app(TenantManager::class)->run($tenant, function () {
            $this->assertTrue(User::where('email', 'm@x.com')->where('role', 'admin')->exists());
            $this->assertGreaterThan(10, Product::count());
        });

        $this->get('/app/koshary-tahrir/login')->assertOk();
        $this->get('/find?code=koshary-tahrir')->assertRedirect('/app/koshary-tahrir/login');
        $this->get('/find?code=nope')->assertRedirect('/');
        $this->get('/app/nope/login')->assertNotFound();

        app(TenantRegistrar::class)->delete($tenant);
        $this->assertFalse(Tenant::where('slug', 'koshary-tahrir')->exists());
    }

    public function test_restaurants_are_fully_isolated(): void
    {
        $alpha = $this->createTenant('alpha');
        Product::first()->update(['name' => 'صنف ألفا السري']);
        $beta = $this->createTenant('beta');

        // بيانات كل مطعم في قاعدته
        $this->getJson('/api/beta/menu')->assertOk()->assertJsonMissing(['name_ar' => 'صنف ألفا السري']);
        $this->getJson('/api/alpha/menu')->assertOk()->assertJsonFragment(['name_ar' => 'صنف ألفا السري']);

        // توكن مطعم ألفا مالوش قيمة في مطعم بيتا
        $token = $this->postJson('/api/alpha/staff/login', ['email' => 'cashier@pos.test', 'password' => 'password'])->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/alpha/orders')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/beta/orders')->assertUnauthorized();

        // جلسة الويب في ألفا ما تفتحش بيتا
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $user = app(TenantManager::class)->run($alpha, fn () => User::firstWhere('email', 'admin@pos.test'));
        $this->actingAs($user, 'web')->withSession(['auth_tenant' => 'alpha']);
        $this->get('/app/alpha/orders')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get('/app/beta/orders')->assertRedirect('/app/beta/login');
    }

    public function test_expired_and_suspended_restaurants_are_blocked(): void
    {
        $tenant = $this->createTenant('late', ['trial_ends_at' => now()->subDay()]);
        $this->assertSame('expired', $tenant->accessState());

        $this->getJson('/api/late/menu')->assertStatus(402)->assertJsonPath('subscription', 'expired');

        $admin = User::firstWhere('email', 'admin@pos.test');
        $this->actingAs($admin)->withSession(['auth_tenant' => 'late']);
        $this->get('/app/late/pos')->assertRedirect('/app/late/billing');
        $this->get('/app/late/billing')->assertOk()->assertSee('انتهى اشتراك المطعم');

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $cashier = User::firstWhere('email', 'cashier@pos.test');
        $this->actingAs($cashier, 'web')->withSession(['auth_tenant' => 'late']);
        $this->get('/app/late/pos')->assertStatus(402);

        $tenant->update(['status' => 'suspended']);
        $this->getJson('/api/late/menu')->assertStatus(402)->assertJsonPath('subscription', 'suspended');
    }

    public function test_plan_limits_are_enforced(): void
    {
        $this->createTenant('small');
        $plan = tenant()->plan;
        $plan->update(['max_products' => Product::count(), 'max_orders_per_month' => 1, 'customer_app' => false]);
        tenant()->refresh();

        $this->expectsLimit(fn () => Product::create(['category_id' => 1, 'name' => 'x', 'price' => 1]));

        // الطيارين: الحد على سجلات الطيارين، مش حسابات دخولهم
        $plan->update(['max_drivers' => \App\Models\Driver::count(), 'max_users' => User::where('role', '!=', 'driver')->count()]);
        tenant()->refresh();
        $this->expectsLimit(fn () => \App\Models\Driver::create(['name' => 'جديد']));
        $this->expectsLimit(fn () => User::create(['name' => 'c2', 'email' => 'c2@x.com', 'password' => 'x', 'role' => 'cashier']));
        User::create(['name' => 'محمود', 'email' => 'd2@x.com', 'password' => 'x', 'role' => 'driver']); // مسموح

        $token = $this->postJson('/api/small/staff/login', ['email' => 'cashier@pos.test', 'password' => 'password'])->json('token');
        $order = ['type' => 'takeaway', 'items' => [['product_id' => Product::first()->id, 'qty' => 1]]];
        $this->withToken($token)->postJson('/api/small/pos/orders', $order)->assertCreated();
        $this->withToken($token)->postJson('/api/small/pos/orders', $order)->assertUnprocessable()->assertJsonValidationErrors('limit');

        // تطبيق العملاء مش في الباقة
        $this->postJson('/api/small/customer/register', ['name' => 'a', 'phone' => '01', 'password' => 'secret12'])->assertForbidden();
    }

    private function expectsLimit(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected plan limit');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('limit', $e->errors());
        }
    }

    public function test_paymob_checkout_webhook_and_return(): void
    {
        config(['tenancy.paymob' => [
            'base_url' => 'https://accept.paymob.com', 'secret_key' => 'sk', 'public_key' => 'pk',
            'hmac_secret' => 'hmac-secret', 'integration_ids' => [123],
        ]]);
        Http::fake(['accept.paymob.com/v1/intention/' => Http::response(['id' => 'pi_1', 'intention_order_id' => 9876, 'client_secret' => 'cs_1'])]);

        $tenant = $this->createTenant('paid', ['trial_ends_at' => now()->subDay()]);
        $plan = Plan::orderBy('sort')->first();
        $this->actingAs(User::firstWhere('email', 'admin@pos.test'))->withSession(['auth_tenant' => 'paid']);

        $this->post('/app/paid/billing/paymob', ['plan_id' => $plan->id, 'months' => 3])
            ->assertRedirect('https://accept.paymob.com/unifiedcheckout/?publicKey=pk&clientSecret=cs_1');

        $payment = Payment::latest('id')->first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('9876', $payment->gateway_order_id);
        $this->assertEquals($plan->priceFor(3), (float) $payment->amount);
        Http::assertSent(fn ($r) => $r['amount'] === (int) ($plan->priceFor(3) * 100) && $r->hasHeader('Authorization', 'Token sk'));

        $tx = [
            'id' => 555, 'amount_cents' => (int) ($plan->priceFor(3) * 100), 'created_at' => '2026-11-28T10:00:00',
            'currency' => 'EGP', 'error_occured' => false, 'has_parent_transaction' => false, 'integration_id' => 123,
            'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false, 'is_refunded' => false,
            'is_standalone_payment' => true, 'is_voided' => false, 'order' => ['id' => 9876], 'owner' => 1,
            'pending' => false, 'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'success' => true,
        ];
        $hmac = $this->hmac($tx);

        $this->postJson('/paymob/webhook?hmac=bad', ['type' => 'TRANSACTION', 'obj' => $tx])->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->postJson('/paymob/webhook?hmac='.$hmac, ['type' => 'TRANSACTION', 'obj' => $tx])->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $tenant->refresh();
        $this->assertSame('active', $tenant->accessState());
        $this->assertTrue($tenant->subscription_ends_at->between(now()->addMonths(3)->subDay(), now()->addMonths(3)->addDay()));

        // تكرار الإشعار ما يجددش مرتين
        $this->postJson('/paymob/webhook?hmac='.$hmac, ['type' => 'TRANSACTION', 'obj' => $tx])->assertOk();
        $this->assertTrue($tenant->fresh()->subscription_ends_at->lt(now()->addMonths(3)->addDay()));

        // رجوع العميل (query string)
        $query = ['order' => 9876, 'source_data_pan' => '2346', 'source_data_sub_type' => 'MasterCard', 'source_data_type' => 'card']
            + collect($tx)->except(['order', 'source_data'])->map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v)->all();
        $this->get('/paymob/return?'.http_build_query($query + ['hmac' => $hmac]))->assertRedirect($tenant->url('billing').'?payment=paid');
    }

    private function hmac(array $tx): string
    {
        $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
            'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner',
            'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
        $data = collect($fields)->map(fn ($f) => data_get($tx, $f))->map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)->implode('');

        return hash_hmac('sha512', $data, 'hmac-secret');
    }

    public function test_super_admin_manages_restaurants_and_payments(): void
    {
        $tenant = $this->createTenant('client', ['trial_ends_at' => now()->subDay()]);
        app(TenantManager::class)->end();

        $this->get('/admin')->assertRedirect('/admin/login');
        $admin = SuperAdmin::create(['name' => 'Boss', 'email' => 'boss@platform.test', 'password' => 'password']);
        $this->actingAs($admin, 'super_admin');
        $this->get('/admin')->assertOk();
        $this->get('/admin/tenants')->assertOk()->assertSee('client');

        Filament::setCurrentPanel('admin');
        $plan = Plan::orderBy('sort')->first();
        Livewire::test(ManageTenants::class)
            ->callTableAction('renew', $tenant, ['plan_id' => $plan->id, 'months' => 12, 'amount' => 2990, 'method' => 'instapay', 'reference' => 'TX1'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('active', $tenant->fresh()->accessState());
        $this->assertSame('paid', Payment::firstWhere('reference', 'TX1')->status);

        Livewire::test(ManageTenants::class)->callTableAction('suspend', $tenant, ['reason' => 'late']);
        $this->assertSame('suspended', $tenant->fresh()->accessState());
        Livewire::test(ManageTenants::class)->callTableAction('unsuspend', $tenant);
        $this->assertSame('active', $tenant->fresh()->accessState());

        // دفعة يدوية من المطعم ← المدير يأكدها
        $pending = $tenant->payments()->create(['plan_id' => $plan->id, 'months' => 1, 'amount' => 299, 'method' => 'wallet', 'status' => 'pending']);
        $before = $tenant->fresh()->subscription_ends_at;
        Livewire::test(ManagePayments::class)->callTableAction('approve', $pending);
        $this->assertSame('paid', $pending->fresh()->status);
        $this->assertTrue($tenant->fresh()->subscription_ends_at->gt($before));

        // مدير المطعم مش بيدخل لوحة المنصة
        $this->app['auth']->forgetGuards();
        $this->actingAs(app(TenantManager::class)->run($tenant, fn () => User::firstWhere('email', 'admin@pos.test')));
        $this->get('/admin/tenants')->assertRedirect('/admin/login');
    }

    public function test_tenants_migrate_command_runs_for_every_restaurant(): void
    {
        $this->createTenant('one');
        $this->createTenant('two');
        $this->artisan('tenants:migrate')->expectsOutputToContain('Migrated 2 restaurant(s), 0 failed.')->assertSuccessful();
        $this->artisan('tenants:migrate', ['--tenant' => ['two']])->expectsOutputToContain('Migrated 1 restaurant(s)')->assertSuccessful();
    }
}
