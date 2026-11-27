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
            self::Cash => 'كاش',
            self::Card => 'فيزا',
            self::Wallet => 'محفظة / إنستاباي',
        };
    }
}
