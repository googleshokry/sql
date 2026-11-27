<?php

namespace App\Filament\Concerns;

/**
 * تقييد الوصول للشاشة حسب دور المستخدم.
 * الكلاس يعرّف: protected static array $roles = ['accountant', 'sales'];
 * المدير (admin) يرى كل شيء. مصفوفة فارغة = متاحة للجميع.
 */
trait HasRoleAccess
{
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return empty(static::$roles) || $user->hasRole(...static::$roles);
    }
}
