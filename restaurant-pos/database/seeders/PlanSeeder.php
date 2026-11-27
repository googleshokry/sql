<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'أساسي', 'name_en' => 'Basic', 'price_monthly' => 299, 'price_yearly' => 2990,
                'max_users' => 3, 'max_drivers' => 3, 'max_products' => 100, 'max_orders_per_month' => 1500, 'customer_app' => false],
            ['name' => 'احترافي', 'name_en' => 'Pro', 'price_monthly' => 599, 'price_yearly' => 5990,
                'max_users' => 10, 'max_drivers' => 15, 'max_products' => 500, 'max_orders_per_month' => 10000, 'customer_app' => true],
            ['name' => 'سلاسل مطاعم', 'name_en' => 'Enterprise', 'price_monthly' => 1499, 'price_yearly' => 14990,
                'max_users' => null, 'max_drivers' => null, 'max_products' => null, 'max_orders_per_month' => null, 'customer_app' => true],
        ];

        foreach ($plans as $sort => $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], [...$plan, 'sort' => $sort]);
        }
    }
}
