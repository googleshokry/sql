<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * القاعدة المركزية: مدير المنصة + الباقات + مطعم تجريبي (demo)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::updateOrCreate(['email' => env('SUPER_ADMIN_EMAIL', 'owner@platform.test')], [
            'name' => 'مدير المنصة',
            'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        ]);

        $this->call(PlanSeeder::class);

        if (! Tenant::where('slug', 'demo')->exists()) {
            $tenant = Tenant::create([
                'name' => 'مطعم تجريبي',
                'slug' => 'demo',
                'database' => TenantManager::databaseNameFor('demo'),
                'owner_name' => 'المدير',
                'owner_email' => 'admin@pos.test',
                'plan_id' => Plan::orderBy('sort')->skip(1)->value('id'),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(config('tenancy.trial_days')),
            ]);
            $manager = app(TenantManager::class);
            $manager->createDatabase($tenant);
            $manager->migrate($tenant);
            $manager->run($tenant, fn () => (new TenantSeeder)->run(withDemoStaff: true));
        }
    }
}
