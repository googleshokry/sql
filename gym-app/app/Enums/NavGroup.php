<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Sidebar groups (case order = display order). */
enum NavGroup implements HasLabel
{
    case Attendance;
    case Memberships;
    case Finance;
    case Store;
    case Admin;

    public function getLabel(): string
    {
        return match ($this) {
            self::Attendance => __('الحضور والانصراف'),
            self::Memberships => __('العضويات'),
            self::Finance => __('المالية'),
            self::Store => __('المتجر والبوفيه'),
            self::Admin => __('الإدارة'),
        };
    }
}
