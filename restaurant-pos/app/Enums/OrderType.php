<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel, HasColor
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function getLabel(): string
    {
        return match ($this) {
            self::DineIn => 'صالة',
            self::Takeaway => 'تيك أواي',
            self::Delivery => 'دليفري',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DineIn => 'info',
            self::Takeaway => 'gray',
            self::Delivery => 'warning',
        };
    }
}
