<?php

namespace App\Models\Concerns;

/**
 * يولّد رقمًا تسلسليًا تلقائيًا للمستند (مثال: INV-000001).
 * الموديل يعرّف: public const NUMBER_PREFIX = 'INV';
 */
trait HasDocumentNumber
{
    public static function bootHasDocumentNumber(): void
    {
        static::creating(function ($model) {
            if (blank($model->number)) {
                $model->number = static::nextNumber();
            }
        });
    }

    public static function nextNumber(?string $prefix = null): string
    {
        $prefix ??= static::NUMBER_PREFIX;
        $last = static::query()->where('number', 'like', $prefix.'-%')->orderByDesc('id')->value('number');
        $seq = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        do {
            $number = $prefix.'-'.str_pad((string) $seq++, 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }
}
