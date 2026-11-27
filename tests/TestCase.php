<?php

namespace Tests;

use App\Models\Company;
use App\Services\Accounting;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Tenancy::forget();
        Tenancy::refresh();
        Accounting::flushCache();

        parent::tearDown();
    }

    /** Make the seeded demo company the current tenant (for direct model access in tests). */
    protected function actAsDemoCompany(): Company
    {
        $company = DatabaseSeeder::demoCompany();
        Tenancy::set($company);

        return $company;
    }
}
