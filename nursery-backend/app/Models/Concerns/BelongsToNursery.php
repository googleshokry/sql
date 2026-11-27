<?php

namespace App\Models\Concerns;

use App\Models\Nursery;
use App\Support\CurrentNursery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every tenant-owned model uses this trait.
 * - Reads are filtered to the current nursery automatically.
 * - nursery_id is filled automatically on create.
 * - With no current nursery (console, queue, platform panel) no filter is applied,
 *   so jobs must call app(CurrentNursery::class)->set(...) when they act for one nursery.
 */
trait BelongsToNursery
{
    public static function bootBelongsToNursery(): void
    {
        static::addGlobalScope('nursery', function (Builder $builder) {
            $current = app(CurrentNursery::class);

            if ($current->check()) {
                $builder->where($builder->qualifyColumn('nursery_id'), $current->id());
            }
        });

        static::creating(function ($model) {
            $current = app(CurrentNursery::class);

            if (blank($model->nursery_id) && $current->check()) {
                $model->nursery_id = $current->id();
            }
        });
    }

    public function nursery(): BelongsTo
    {
        return $this->belongsTo(Nursery::class);
    }
}
