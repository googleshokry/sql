<?php

namespace App\Support;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/** Validation rules limited to the current company. */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('company_id', Tenancy::id());
    }

    public static function unique(string $table, string $column): Unique
    {
        return (new Unique($table, $column))->where('company_id', Tenancy::id());
    }
}
