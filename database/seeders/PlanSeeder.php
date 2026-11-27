<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Default subscription plans (edit prices and limits from the platform panel). */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'basic', 'name' => 'الأساسية', 'name_en' => 'Basic', 'monthly_price' => 99, 'yearly_price' => 990,
                'max_users' => 2, 'max_branches' => 1, 'max_warehouses' => 1, 'sort' => 1,
                'features' => ['pos', 'purchases', 'accounting'],
            ],
            [
                'code' => 'business', 'name' => 'الأعمال', 'name_en' => 'Business', 'monthly_price' => 249, 'yearly_price' => 2490,
                'max_users' => 10, 'max_branches' => 3, 'max_warehouses' => 5, 'sort' => 2,
                'features' => ['pos', 'purchases', 'accounting', 'multi_warehouse', 'installments', 'crm', 'mobile_app'],
            ],
            [
                'code' => 'enterprise', 'name' => 'المؤسسات', 'name_en' => 'Enterprise', 'monthly_price' => 499, 'yearly_price' => 4990,
                'max_users' => 0, 'max_branches' => 0, 'max_warehouses' => 0, 'sort' => 3,
                'features' => array_keys(Plan::FEATURES),
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan + ['trial_days' => 14, 'is_active' => true]);
        }
    }
}
