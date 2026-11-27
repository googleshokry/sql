<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Driver = 'driver';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'مدير',
            self::Cashier => 'كاشير',
            self::Driver => 'طيار',
        };
    }
}
