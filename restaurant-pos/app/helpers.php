<?php

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;

if (! function_exists('tenant')) {
    /** المطعم الحالي (أو null خارج أي مطعم) */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current();
    }
}
