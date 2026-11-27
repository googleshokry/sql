<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Posting;
use Illuminate\Database\Seeder;

/** بيانات تجريبية: أصناف وعملاء وموردين وفاتورة مشتريات مُرحّلة */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $unit = Unit::query()->first();
        $cat = Category::query()->where('name', 'إلكترونيات')->first();

        $products = [
            ['لابتوب HP ProBook', '6281000000011', 2500, 3200],
            ['شاشة سامسونج 24 بوصة', '6281000000028', 450, 650],
            ['ماوس لاسلكي لوجيتك', '6281000000035', 45, 85],
            ['كيبورد ميكانيكي', '6281000000042', 120, 199],
            ['سماعة بلوتوث', '6281000000059', 80, 149],
            ['طابعة كانون', '6281000000066', 600, 850],
        ];
        foreach ($products as [$name, $barcode, $cost, $price]) {
            Product::create([
                'name' => $name, 'barcode' => $barcode, 'category_id' => $cat?->id, 'unit_id' => $unit?->id,
                'purchase_price' => $cost, 'sale_price' => $price, 'wholesale_price' => round($price * 0.9), 'tax_rate' => 15, 'min_stock' => 3,
            ]);
        }
        Product::create(['name' => 'خدمة صيانة', 'type' => 'service', 'sale_price' => 150, 'tax_rate' => 15, 'category_id' => Category::query()->where('name', 'خدمات')->value('id')]);

        foreach ([['شركة النور للتجارة', '0551234567', '300000000000003'], ['مؤسسة الأمل', '0559876543', null], ['أحمد محمد', '01001234567', null]] as [$name, $phone, $vat]) {
            Customer::create(['name' => $name, 'phone' => $phone, 'tax_number' => $vat, 'city' => 'الرياض']);
        }
        $supplier = Supplier::create(['name' => 'شركة التقنية الحديثة', 'phone' => '0112345678', 'tax_number' => '310000000000003']);

        $invoice = PurchaseInvoice::create(['date' => now()->toDateString(), 'supplier_id' => $supplier->id]);
        foreach (Product::query()->where('type', 'product')->get() as $p) {
            $invoice->items()->create(['product_id' => $p->id, 'quantity' => 20, 'unit_price' => $p->purchase_price, 'tax_rate' => 15]);
        }
        Posting::post($invoice->refresh());
    }
}
