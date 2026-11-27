<?php

namespace App\Filament\Support;

use App\Models\Setting;

class Money
{
    public static function currency(): string
    {
        return (string) Setting::get('currency', 'ر.س');
    }

    public static function format(float|string|null $amount): string
    {
        return number_format((float) $amount, 2).' '.static::currency();
    }
}
