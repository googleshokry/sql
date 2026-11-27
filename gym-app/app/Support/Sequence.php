<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Per-gym running numbers (receipts, invoices, membership codes). */
class Sequence
{
    /**
     * Next number for `$column` among records of the same gym, e.g. R000012 -> 13.
     *
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, int $gymId, string $column, int $start = 1): int
    {
        $max = $model::withoutGlobalScopes()
            ->where('gym_id', $gymId)
            ->where($column, 'not like', 'TMP-%')
            ->pluck($column)
            ->map(fn ($value) => (int) preg_replace('/\D/', '', (string) $value))
            ->max();

        return max($start, (int) $max + 1);
    }
}
