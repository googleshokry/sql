<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Pages\Subscription;
use App\Filament\Platform\Resources\CompanyResource\Pages\ListCompanies;
use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\UserResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioner;
use App\Services\SalesCheckout;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->companyA = DatabaseSeeder::demoCompany();

        $this->ownerB = CompanyProvisioner::register([
            'company_name' => 'Company B', 'name' => 'Owner B', 'email' => 'b@example.com', 'password' => 'password', 'locale' => 'en',
        ], Plan::query()->where('code', 'enterprise')->first());
        $this->companyB = $this->ownerB->company;
    }

    protected function saleFor(Company $company): SalesInvoice
    {
        return Tenancy::run($company, function () {
            $product = Product::query()->first() ?? Product::create(['name' => 'Item', 'type' => 'service', 'sale_price' => 10]);

            return SalesCheckout::checkout(
                lines: [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
                customerId: Customer::query()->value('id'),
                warehouseId: Warehouse::query()->value('id'),
                treasuryId: null,
            );
        });
    }

    public function test_new_company_is_provisioned_with_its_own_books(): void
    {
        $this->assertSame('trial', $this->companyB->status);
        $this->assertTrue($this->companyB->trial_ends_at->isFuture());

        Tenancy::run($this->companyB, function () {
            $this->assertSame(count(CompanyProvisioner::CHART_OF_ACCOUNTS) + 2, Account::query()->count(), 'chart + one sub-account per treasury');
            $this->assertSame('Cash customer', Customer::query()->value('name'));
            $this->assertSame(0, Product::query()->count());
        });
        Tenancy::run($this->companyA, fn () => $this->assertGreaterThan(0, Product::query()->count()));
    }

    public function test_document_numbers_and_codes_are_per_company(): void
    {
        $a = $this->saleFor($this->companyA);
        $this->saleFor($this->companyA);
        $b = $this->saleFor($this->companyB);

        $this->assertSame('INV-000001', $a->number);
        $this->assertSame('INV-000001', $b->number);
        Tenancy::run($this->companyB, fn () => $this->assertSame('C00001', Customer::query()->value('code')));
    }

    public function test_company_cannot_see_or_use_another_companys_data_in_the_panel(): void
    {
        $invoiceA = $this->saleFor($this->companyA);

        $this->actingAs($this->ownerB);
        $this->get(SalesInvoiceResource::getUrl('view', ['record' => $invoiceA]))->assertNotFound();
        $this->get(SalesInvoiceResource::getUrl('index'))->assertOk()->assertDontSee($invoiceA->number);
        $this->get(route('print.document', ['sales-invoice', $invoiceA->id]))->assertNotFound();

        Filament::setCurrentPanel('admin');
        $this->assertSame([$this->ownerB->id], UserResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_company_cannot_see_or_use_another_companys_data_in_the_api(): void
    {
        $invoiceA = $this->saleFor($this->companyA);
        $warehouseA = Tenancy::run($this->companyA, fn () => Warehouse::query()->value('id'));
        $productA = Tenancy::run($this->companyA, fn () => Product::query()->where('type', 'product')->value('id'));

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'password'])->assertOk()->json('token');
        $api = $this->withToken($token);

        $api->getJson("/api/v1/sales-invoices/{$invoiceA->id}")->assertNotFound();
        $api->getJson('/api/v1/sales-invoices')->assertOk()->assertJsonCount(0, 'data');
        $api->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $api->postJson('/api/v1/sales-invoices', [
            'warehouse_id' => $warehouseA,
            'items' => [['product_id' => $productA, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['warehouse_id', 'items.0.product_id']);
    }

    public function test_sign_up_creates_a_company_on_trial(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::test(Register::class)
            ->fillForm([
                'company_name' => 'مؤسسة النجاح',
                'name' => 'أحمد',
                'phone' => '0500000000',
                'email' => 'new@example.com',
                'password' => 'secret-password-1',
                'passwordConfirmation' => 'secret-password-1',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('مؤسسة النجاح', $user->company->name);
        $this->assertSame('admin', $user->role);
        $this->assertSame('trial', $user->company->status);
        $this->assertNotNull($user->company->plan_id);
        Tenancy::run($user->company, fn () => $this->assertSame(2, Treasury::query()->count()));
    }

    public function test_expired_subscription_blocks_panel_and_api(): void
    {
        $this->companyB->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);

        $this->actingAs($this->ownerB)->get('/admin/sales-invoices')->assertRedirect(Subscription::getUrl());
        $this->get(Subscription::getUrl())->assertOk()->assertSee('Subscription');

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'password'])->json('token');
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertStatus(402)->assertJsonPath('subscription_expired', true);
    }

    public function test_plan_controls_modules_and_limits(): void
    {
        $basic = Plan::query()->where('code', 'basic')->first();
        $this->companyB->update(['plan_id' => $basic->id]);

        $this->actingAs($this->ownerB);
        $this->get('/admin/employees')->assertForbidden();
        $this->get('/admin/installment-plans')->assertForbidden();
        $this->get('/admin/point-of-sale')->assertOk();

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'password'])->json('token');
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertForbidden();

        Filament::setCurrentPanel('admin');
        $this->assertTrue(UserResource::canCreate());
        User::create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'password', 'role' => 'sales']);
        Tenancy::refresh();
        $this->assertFalse(UserResource::canCreate(), 'basic plan allows 2 users');
        $this->assertSame($this->companyB->id, User::query()->where('email', 'second@example.com')->value('company_id'));
    }

    public function test_platform_panel_is_only_for_the_saas_owner(): void
    {
        $super = User::query()->where('email', 'super@admin.com')->firstOrFail();

        $this->actingAs($this->ownerB)->get('/platform')->assertForbidden();

        $this->actingAs($super);
        $this->get('/admin')->assertForbidden();
        $this->get('/platform')->assertOk();
        $this->get('/platform/companies')->assertOk()->assertSee('Company B');
        $this->get('/platform/plans')->assertOk();

        Filament::setCurrentPanel('platform');
        Tenancy::withoutTenancy(fn () => Livewire::test(ListCompanies::class)
            ->callTableAction('extend', $this->companyB, ['months' => 12, 'plan_id' => $this->companyB->plan_id]));

        $this->companyB->refresh();
        $this->assertSame('active', $this->companyB->status);
        $this->assertSame('yearly', $this->companyB->billing_cycle);
        $this->assertTrue($this->companyB->subscription_ends_at->isAfter(now()->addMonths(11)));
    }

    public function test_signed_print_link_works_without_login_only_for_its_company(): void
    {
        $invoiceA = $this->saleFor($this->companyA);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'admin@admin.com', 'password' => 'password'])->json('token');
        $printUrl = $this->withToken($token)->getJson("/api/v1/sales-invoices/{$invoiceA->id}")->json('data.print_url');

        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->get($printUrl)->assertOk()->assertSee($invoiceA->number);
        $this->get(str_replace('company='.$this->companyA->id, 'company='.$this->companyB->id, $printUrl))->assertForbidden();
    }
}
