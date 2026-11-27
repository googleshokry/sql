<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2).' '.Setting::currency();
    }
}
