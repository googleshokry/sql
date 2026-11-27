<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Treasury;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => 'admin@admin.com', 'password' => 'password', 'device_name' => 'test'])
            ->assertOk()->json('token');
    }

    public function test_login_rejects_wrong_password_in_requested_language(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@admin.com', 'password' => 'wrong'], ['Accept-Language' => 'en'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Invalid login credentials');

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@admin.com', 'password' => 'wrong'], ['Accept-Language' => 'ar'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'بيانات الدخول غير صحيحة');
    }

    public function test_protected_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_deactivated_user_token_is_rejected(): void
    {
        $token = $this->token();
        User::query()->where('email', 'admin@admin.com')->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_full_mobile_sale_flow(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token()];

        $this->getJson('/api/v1/lookups', $headers)->assertOk()->assertJsonStructure(['company', 'currency', 'warehouses', 'treasuries', 'payment_methods']);
        $this->getJson('/api/v1/dashboard', $headers)->assertOk()->assertJsonStructure(['sales_today', 'cash_balance', 'last_7_days']);

        $product = Product::query()->whereNotNull('barcode')->first();
        $this->getJson('/api/v1/products/barcode/'.$product->barcode, $headers)->assertOk()->assertJsonPath('data.id', $product->id);
        $this->getJson('/api/v1/products?search=ماوس', $headers)->assertOk()->assertJsonCount(1, 'data');

        $customerId = $this->postJson('/api/v1/customers', ['name' => 'عميل الجوال', 'phone' => '0500000000'], $headers)
            ->assertCreated()->json('data.id');

        $invoice = $this->postJson('/api/v1/sales-invoices', [
            'customer_id' => $customerId,
            'warehouse_id' => Warehouse::query()->value('id'),
            'treasury_id' => Treasury::query()->value('id'),
            'paid' => 100,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 15]],
        ], $headers)->assertCreated()->assertJsonPath('data.total', 230)->assertJsonPath('data.payment_status', 'partial')->json('data');

        $this->assertEquals(130, Customer::find($customerId)->balance());
        $this->assertEquals(18, $product->fresh()->quantity());

        $this->postJson("/api/v1/sales-invoices/{$invoice['id']}/payments", ['amount' => 130, 'treasury_id' => Treasury::query()->value('id')], $headers)
            ->assertCreated();
        $this->getJson("/api/v1/sales-invoices/{$invoice['id']}", $headers)->assertJsonPath('data.payment_status', 'paid')->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/customers/{$customerId}/statement", $headers)->assertOk()->assertJsonPath('closing', 0)->assertJsonCount(3, 'rows');

        $this->get($invoice['print_url'])->assertOk()->assertSee($invoice['number']);
        $this->get(str_replace('signature=', 'signature=x', $invoice['print_url']))->assertForbidden();
    }

    public function test_sale_more_than_stock_returns_localized_error(): void
    {
        $product = Product::query()->where('type', 'product')->first();

        $this->withToken($this->token())->postJson('/api/v1/sales-invoices', [
            'warehouse_id' => Warehouse::query()->value('id'),
            'treasury_id' => Treasury::query()->value('id'),
            'paid' => 999999,
            'items' => [['product_id' => $product->id, 'quantity' => 500, 'unit_price' => 1]],
        ], ['Accept-Language' => 'en'])->assertStatus(422)->assertJsonFragment(['message' => "Insufficient quantity of [{$product->name}] in the warehouse (available: 20)"]);
    }

    public function test_expense_and_receipt_from_app(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token()];
        $treasury = Treasury::query()->value('id');

        $this->postJson('/api/v1/expenses', ['expense_category_id' => 1, 'treasury_id' => $treasury, 'amount' => 50, 'tax_rate' => 15], $headers)
            ->assertCreated()->assertJsonPath('data.total', 57.5);

        $customer = Customer::query()->first();
        $this->postJson('/api/v1/payments', ['customer_id' => $customer->id, 'treasury_id' => $treasury, 'amount' => 40], $headers)
            ->assertCreated()->assertJsonPath('data.direction', 'in');
        $this->assertEquals(-40, $customer->balance());
    }
}
