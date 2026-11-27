<?php

namespace App\Support;

/**
 * Nursery-scoped roles (spatie/laravel-permission, teams mode, team = nursery).
 * Super admin is a flag on users (is_super_admin), not a role.
 */
final class Roles
{
    public const NURSERY_ADMIN = 'nursery_admin';
    public const RECEPTIONIST = 'receptionist';
    public const TEACHER = 'teacher';
    public const GUARDIAN = 'guardian';

    public const ALL = [self::NURSERY_ADMIN, self::RECEPTIONIST, self::TEACHER, self::GUARDIAN];

    public const STAFF = [self::NURSERY_ADMIN, self::RECEPTIONIST, self::TEACHER];
}
