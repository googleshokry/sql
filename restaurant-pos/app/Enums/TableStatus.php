<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TableStatus: string implements HasLabel, HasColor
{
    case Available = 'available';
    case Occupied = 'occupied';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => __('متاحة'),
            self::Occupied => __('مشغولة'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'danger',
        };
    }
}
