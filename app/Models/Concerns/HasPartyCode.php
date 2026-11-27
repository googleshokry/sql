<?php

namespace App\Models\Concerns;

trait HasPartyCode
{
    public static function bootHasPartyCode(): void
    {
        static::creating(function ($model) {
            if (blank($model->code)) {
                $prefix = static::CODE_PREFIX;
                $next = (int) static::query()->max('id') + 1;
                $model->code = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
