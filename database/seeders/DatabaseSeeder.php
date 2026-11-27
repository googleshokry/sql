<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompanyProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        // Platform owner: manages companies, plans and subscriptions at /platform
        User::query()->firstOrCreate(['email' => 'super@admin.com'], [
            'name' => 'Platform Admin',
            'password' => 'password',
            'role' => 'admin',
            'is_super_admin' => true,
        ]);

        // Demo company with its owner (admin@admin.com)
        if (! User::query()->where('email', 'admin@admin.com')->exists()) {
            $owner = CompanyProvisioner::register([
                'company_name' => 'شركتي',
                'name' => 'مدير النظام',
                'email' => 'admin@admin.com',
                'password' => 'password',
                'locale' => 'ar',
            ], Plan::query()->where('code', 'enterprise')->first());

            $owner->company->update(['status' => 'active', 'subscription_ends_at' => now()->addYear()]);
        }

        if (env('SEED_DEMO_DATA', app()->isLocal()) && ! app()->runningUnitTests()) {
            $this->call(DemoSeeder::class);
        }
    }

    /** The demo company created above. */
    public static function demoCompany(): Company
    {
        return User::query()->where('email', 'admin@admin.com')->firstOrFail()->company;
    }
}
