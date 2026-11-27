<?php

namespace Tests;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @var array<Tenant> */
    private array $createdTenants = [];

    /**
     * ينشئ مطعم بقاعدة بيانات خاصة (ملف SQLite أو قاعدة MySQL) ويحول له.
     */
    protected function createTenant(string $slug = 'test', array $attributes = [], bool $demoStaff = true): Tenant
    {
        if (! Plan::exists()) {
            $this->seed(PlanSeeder::class);
        }

        $tenant = Tenant::create([
            'name' => 'مطعم '.$slug,
            'slug' => $slug,
            // اسم فريد عشان الاختبارات المتوازية وما يتعارضش مع قواعد حقيقية
            'database' => TenantManager::databaseNameFor('t_'.$slug.'_'.Str::lower(Str::random(6))),
            'owner_name' => 'المدير',
            'owner_email' => 'admin@pos.test',
            'plan_id' => Plan::orderBy('sort')->skip(1)->value('id'),
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            ...$attributes,
        ]);

        $manager = app(TenantManager::class);
        $manager->createDatabase($tenant);
        $this->createdTenants[] = $tenant;
        $manager->migrate($tenant);
        $manager->run($tenant, fn () => (new TenantSeeder)->run(withDemoStaff: $demoStaff));
        $manager->initialize($tenant);

        return $tenant;
    }

    protected function tearDown(): void
    {
        if ($this->createdTenants && $this->app) {
            $manager = app(TenantManager::class);
            foreach ($this->createdTenants as $tenant) {
                $manager->dropDatabase($tenant);
            }
            $this->createdTenants = [];
        }

        parent::tearDown();
    }
}
