<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);

        Setting::setMany(Setting::DEFAULTS);

        $branch = Branch::query()->firstOrCreate(['name' => 'الفرع الرئيسي']);
        Warehouse::query()->firstOrCreate(['name' => 'المخزن الرئيسي'], ['branch_id' => $branch->id, 'is_default' => true]);

        if (! Treasury::query()->exists()) {
            Treasury::create(['name' => 'الخزينة الرئيسية', 'type' => 'cash', 'branch_id' => $branch->id, 'is_default' => true]);
            Treasury::create(['name' => 'البنك', 'type' => 'bank', 'branch_id' => $branch->id]);
            Treasury::create(['name' => 'مدى / شبكة', 'type' => 'wallet', 'branch_id' => $branch->id]);
        }

        User::query()->firstOrCreate(['email' => 'admin@admin.com'], [
            'name' => 'مدير النظام',
            'password' => 'password',
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        foreach (['قطعة' => 'قطعة', 'كرتونة' => 'كرتونة', 'كيلوجرام' => 'كجم', 'لتر' => 'لتر', 'متر' => 'م', 'ساعة' => 'ساعة'] as $name => $short) {
            Unit::query()->firstOrCreate(['name' => $name], ['short_name' => $short]);
        }

        foreach (['عام', 'إلكترونيات', 'مواد غذائية', 'خدمات'] as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }

        $expenseCategories = [
            'إيجار' => 'rent', 'كهرباء ومياه' => 'utilities', 'دعاية وإعلان' => 'marketing',
            'نقل ومواصلات' => 'transport', 'مصروفات نثرية' => 'general_expenses',
        ];
        foreach ($expenseCategories as $name => $key) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['account_id' => Accounting::account($key)->id]);
        }

        foreach (['الإدارة', 'المبيعات', 'المخازن', 'المحاسبة'] as $name) {
            Department::query()->firstOrCreate(['name' => $name]);
        }

        Customer::query()->firstOrCreate(['name' => 'عميل نقدي'], ['phone' => '-']);
        Supplier::query()->firstOrCreate(['name' => 'مورد عام'], ['phone' => '-']);

        if (env('SEED_DEMO_DATA', app()->isLocal()) && ! app()->runningUnitTests()) {
            $this->call(DemoSeeder::class);
        }
    }
}
