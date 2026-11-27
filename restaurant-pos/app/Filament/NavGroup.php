<?php

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/** مجموعات القائمة الجانبية (مترجمة) */
enum NavGroup implements HasLabel
{
    case Sales;
    case Menu;
    case Delivery;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sales => __('المبيعات'),
            self::Menu => __('المنيو'),
            self::Delivery => __('الدليفري'),
            self::Settings => __('الإعدادات'),
        };
    }
}
