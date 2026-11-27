<?php

namespace Database\Seeders;

use App\Services\CompanyProvisioner;
use Illuminate\Database\Seeder;

/**
 * Re-installs the default chart of accounts for the current company.
 * Usage: wrap in Tenancy::run($company, ...) — new companies get it automatically on sign-up.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        CompanyProvisioner::installChartOfAccounts();
    }
}
