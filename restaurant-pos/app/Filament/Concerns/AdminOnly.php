<?php

namespace App\Filament\Concerns;

/** يقصر الوصول على المدير فقط */
trait AdminOnly
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }
}
