<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Posting;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** يفتح كل شاشات لوحة التحكم ويتأكد أنها تعمل بدون أخطاء */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_every_admin_page_renders_in_arabic_and_english(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->assertEveryPageRenders($locale);
        }
    }

    protected function assertEveryPageRenders(string $locale): void
    {
        $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $admin->update(['locale' => $locale]);
        $this->actingAs($admin)->withSession(['locale' => $locale]);

        $records = [
            'customer' => Customer::query()->first(),
            'product' => Product::query()->first(),
            'purchase-invoice' => PurchaseInvoice::query()->first(),
        ];

        $failures = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';
            if (! str_starts_with($name, 'filament.admin.') || ! in_array('GET', $route->methods())) {
                continue;
            }
            if (str_contains($name, '.auth.') || str_contains($name, 'tenant')) {
                continue;
            }

            $params = [];
            if (in_array('record', $route->parameterNames())) {
                $resource = str($name)->after('resources.')->before('.')->singular()->toString();
                $record = $records[$resource] ?? null;
                if (! $record) {
                    continue;
                }
                $params['record'] = $record;
            }

            $response = $this->get(route($name, $params));
            if ($response->status() !== 200 && ! ($response->status() === 403 && str_ends_with($name, '.edit'))) {
                $failures[] = "[$locale] $name => {$response->status()} ".substr(strip_tags((string) $response->exception?->getMessage()), 0, 300);
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_panel_switches_to_english(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());

        $this->get(route('locale.switch', 'en'))->assertRedirect();
        $this->assertSame('en', User::query()->where('email', 'admin@admin.com')->value('locale'));

        $this->get('/admin')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Point of sale (POS)')->assertSee('Chart of accounts')->assertDontSee('فواتير المبيعات');
        $this->get('/admin/sales-invoices/create')->assertOk()->assertSee('Document details')->assertDontSee('بيانات المستند');
        $this->get('/admin/trial-balance')->assertOk()->assertSee('Trial balance');

        $invoice = PurchaseInvoice::query()->firstOrFail();
        $this->get(route('print.document', ['purchase-invoice', $invoice->id]))->assertOk()->assertSee('Purchase invoice')->assertSee('dir="ltr"', false);
    }

    public function test_print_views_render(): void
    {
        $this->actingAs(User::query()->firstOrFail());
        $invoice = PurchaseInvoice::query()->firstOrFail();

        $this->get(route('print.document', ['purchase-invoice', $invoice->id]))->assertOk()->assertSee($invoice->number);
        $this->get(route('print.barcodes', ['ids' => Product::query()->pluck('id')->implode(',')]))->assertOk();
    }

    public function test_sales_invoice_view_and_print_with_qr(): void
    {
        $this->actingAs(User::query()->firstOrFail());
        $invoice = SalesInvoice::create(['date' => now()->toDateString(), 'customer_id' => Customer::query()->first()->id]);
        $invoice->items()->create(['product_id' => Product::query()->first()->id, 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]);
        Posting::post($invoice->refresh());

        $this->get(SalesInvoiceResource::getUrl('view', ['record' => $invoice]))->assertOk();
        $this->get(SalesInvoiceResource::getUrl('edit', ['record' => $invoice]))->assertForbidden();
        $this->get(route('print.document', ['sales-invoice', $invoice->id]))->assertOk()->assertSee('data:image/svg+xml;base64', false);
        $this->get(route('print.document', ['sales-invoice', $invoice->id, 'format' => 'thermal']))->assertOk();
    }
}
