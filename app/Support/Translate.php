<?php

namespace App\Support;

class Translate
{
    /**
     * Translate the labels of a [value => label] options array.
     *
     * @param  array<array-key, string>  $options
     * @return array<array-key, string>
     */
    public static function options(array $options): array
    {
        return array_map(fn (string $label): string => __($label), $options);
    }
}
