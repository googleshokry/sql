<?php

namespace App\Filament\Concerns;

trait ManagerOnly
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }
}
