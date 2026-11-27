<?php

namespace Tests\Feature;

use App\Filament\Pages\PointOfSale;
use App\Filament\Pages\Reports\BalanceSheet;
use App\Filament\Pages\Reports\TrialBalance;
use App\Filament\Pages\Settings;
use App\Filament\Resources\JournalEntryResource\Pages\CreateJournalEntry;
use App\Filament\Resources\PaymentResource\Pages\CreatePayment;
use App\Filament\Resources\PayrollResource\Pages\CreatePayroll;
use App\Filament\Resources\PayrollResource\Pages\ViewPayroll;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InstallmentPlan;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\SalaryAdvance;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\Treasury;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->actAsDemoCompany();
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->first());
    }

    protected function assertLedgerBalanced(): void
    {
        $this->assertEqualsWithDelta((float) JournalEntryLine::sum('debit'), (float) JournalEntryLine::sum('credit'), 0.01, 'دفتر اليومية غير متوازن');
    }

    public function test_create_post_and_collect_sales_invoice_through_ui(): void
    {
        $undo = Repeater::fake();
        $product = Product::query()->where('type', 'product')->first();
        $customer = Customer::query()->where('name', 'شركة النور للتجارة')->first();
        $stockBefore = $product->quantity();

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'date' => now()->toDateString(),
                'warehouse_id' => Warehouse::query()->value('id'),
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_rate' => 15],
                ],
                'discount_type' => 'fixed',
                'discount_value' => 100,
                'shipping' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $invoice = SalesInvoice::query()->latest('id')->first();
        $this->assertEquals(1900, (float) $invoice->subtotal - (float) $invoice->discount_amount);
        $this->assertEquals(285, (float) $invoice->tax_amount);
        $this->assertEquals(2185, (float) $invoice->total);

        Livewire::test(ViewSalesInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('post')
            ->assertNotified();

        $invoice->refresh();
        $this->assertTrue($invoice->isPosted());
        $this->assertEquals($stockBefore - 2, $product->fresh()->quantity());
        $this->assertEquals(2185, $customer->balance());

        Livewire::test(ViewSalesInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('registerPayment', ['amount' => 1000, 'treasury_id' => Treasury::query()->value('id'), 'method' => 'cash', 'date' => now()->toDateString()]);

        $this->assertEquals('partial', $invoice->fresh()->payment_status);
        $this->assertEquals(1185, $customer->balance());

        Livewire::test(ViewSalesInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('installmentPlan', ['installments_count' => 3, 'interest_percent' => 0, 'period' => 'monthly', 'start_date' => now()->addMonth()->toDateString()]);
        $plan = InstallmentPlan::query()->firstOrFail();
        $this->assertCount(3, $plan->installments);
        $this->assertEquals(1185, $plan->installments->sum('amount'));

        $plan->installments->first()->collect(Treasury::query()->value('id'));
        $this->assertEquals(1185 - 395, $customer->balance());

        $this->assertLedgerBalanced();
    }

    public function test_pos_checkout_with_change(): void
    {
        $product = Product::query()->where('type', 'product')->first();

        Livewire::test(PointOfSale::class)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id)
            ->set('paid', 100000)
            ->call('checkout')
            ->assertNotified();

        $invoice = SalesInvoice::query()->where('is_pos', true)->firstOrFail();
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals(2, (float) $invoice->items->first()->quantity);
        $this->assertEquals(round(2 * 3200 * 1.15, 2), (float) $invoice->total);
        $this->assertLedgerBalanced();
    }

    public function test_pos_blocks_selling_more_than_stock(): void
    {
        $product = Product::query()->where('type', 'product')->first();
        $component = Livewire::test(PointOfSale::class)->call('addProduct', $product->id);
        $component->set('cart.0.quantity', 1000)->call('payExact')->call('checkout');

        $this->assertEquals(0, SalesInvoice::query()->count());
    }

    public function test_receipt_voucher_and_manual_journal_entry(): void
    {
        $customer = Customer::query()->first();

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'direction' => 'in', 'date' => now()->toDateString(), 'treasury_id' => Treasury::query()->value('id'),
                'party_type' => Customer::class, 'party_id' => $customer->id, 'amount' => 500, 'method' => 'cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertEquals(-500, $customer->balance());
        $this->assertStringStartsWith('RCV-', Payment::query()->first()->number);

        $undo = Repeater::fake();
        $cash = Treasury::query()->first()->account_id;
        $capital = Accounting::account('capital')->id;
        Livewire::test(CreateJournalEntry::class)
            ->fillForm([
                'date' => now()->toDateString(), 'description' => 'إيداع رأس المال',
                'lines' => [
                    ['account_id' => $cash, 'debit' => 50000, 'credit' => 0],
                    ['account_id' => $capital, 'debit' => 0, 'credit' => 50000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();
        $this->assertEquals(50000, Account::find($capital)->balance());
        $this->assertLedgerBalanced();
    }

    public function test_product_with_opening_stock(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'صنف جديد', 'type' => 'product', 'purchase_price' => 10, 'sale_price' => 15, 'tax_rate' => 15,
                'track_stock' => true, 'opening_quantity' => 50, 'opening_warehouse_id' => Warehouse::query()->value('id'), 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'صنف جديد')->firstOrFail();
        $this->assertEquals(50, $product->quantity());
        $this->assertLedgerBalanced();
    }

    public function test_payroll_generation_and_posting(): void
    {
        $employee = Employee::create(['name' => 'محمد علي', 'basic_salary' => 5000, 'housing_allowance' => 1000]);
        SalaryAdvance::create(['employee_id' => $employee->id, 'treasury_id' => Treasury::query()->value('id'), 'date' => now()->toDateString(), 'amount' => 1200, 'monthly_deduction' => 400]);

        Livewire::test(CreatePayroll::class)
            ->fillForm(['month' => now()->format('Y-m'), 'treasury_id' => Treasury::query()->value('id')])
            ->call('create')
            ->assertHasNoFormErrors();

        $payroll = Payroll::query()->firstOrFail();
        $this->assertEquals(5600, (float) $payroll->total);

        Livewire::test(ViewPayroll::class, ['record' => $payroll->getRouteKey()])->callAction('post');
        $this->assertTrue($payroll->fresh()->isPosted());
        $this->assertEquals(400, (float) $employee->advances()->first()->deducted_amount);
        $this->assertLedgerBalanced();
    }

    public function test_settings_save_and_reports_balance(): void
    {
        Livewire::test(Settings::class)
            ->fillForm(['company_name' => 'مؤسسة الاختبار', 'currency' => 'ج.م', 'default_tax_rate' => 14, 'zatca_qr' => false, 'allow_negative_stock' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEquals('مؤسسة الاختبار', Setting::get('company_name'));

        Livewire::test(TrialBalance::class)->assertSee('الميزان متوازن');
        Livewire::test(BalanceSheet::class)->assertSee('الميزانية متوازنة');
    }
}
