<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_translation_key_used_in_code_has_an_english_value(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $files = array_merge(
            glob(app_path('{,*/,*/*/,*/*/*/,*/*/*/*/}*.php'), GLOB_BRACE),
            glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE),
        );
        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (! array_key_exists($key, $en)) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_web_panel_switches_language_and_remembers_it(): void
    {
        $admin = User::firstWhere('email', 'admin@pos.test');
        $this->actingAs($admin);

        $this->get('/admin/orders')->assertOk()->assertSee('dir="rtl"', false)->assertSee('الطلبات');

        $this->get('/locale/en')->assertRedirect();
        $this->assertSame('en', $admin->fresh()->locale);

        $this->get('/admin/orders')->assertOk()->assertSee('dir="ltr"', false)->assertSee('App orders')->assertSee('Sales')->assertSee('Settings')->assertDontSee('الإعدادات')->assertDontSee('طلبات التطبيق');
        $this->get('/admin/pos')->assertOk()->assertSee('Pay &amp; print', false)->assertSee('Chicken Shawarma');

        $this->get('/locale/xx')->assertNotFound();
    }

    public function test_api_follows_accept_language(): void
    {
        $this->getJson('/api/menu', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.0.name', 'Sandwiches')
            ->assertJsonPath('data.0.products.0.name_en', fn ($v) => is_string($v));
        $this->getJson('/api/delivery-areas', ['Accept-Language' => 'en'])->assertJsonFragment(['name' => 'Nasr City']);
        $this->getJson('/api/menu', ['Accept-Language' => 'en'])
            ->assertJsonPath('data.0.products.0.name_ar', fn ($v) => is_string($v));
        $this->getJson('/api/menu', ['Accept-Language' => 'ar'])->assertJsonPath('data.0.name', 'سندوتشات');

        $this->postJson('/api/staff/login', ['email' => 'admin@pos.test', 'password' => 'x'], ['Accept-Language' => 'en'])
            ->assertJsonPath('errors.email.0', 'Invalid login details');
        $this->postJson('/api/customer/register', [], ['Accept-Language' => 'ar'])
            ->assertJsonPath('errors.phone.0', 'حقل الموبايل مطلوب.');

        $token = $this->postJson('/api/staff/login', ['email' => 'cashier@pos.test', 'password' => 'password'])->json('token');
        $p = Product::firstWhere('name_en', 'Tea');
        $this->withToken($token)->postJson('/api/pos/orders', [
            'type' => 'takeaway', 'items' => [['product_id' => $p->id, 'qty' => 1]],
        ], ['Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.type_label', 'Takeaway')
            ->assertJsonPath('data.status_label', 'Preparing')
            ->assertJsonPath('data.items.0.name', 'Tea');
    }
}
