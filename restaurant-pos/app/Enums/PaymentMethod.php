<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Wallet = 'wallet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('كاش'),
            self::Card => __('فيزا'),
            self::Wallet => __('محفظة / إنستاباي'),
        };
    }
}
