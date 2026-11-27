<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation: scopes all queries to the current gym and fills gym_id on create.
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope('gym', function (Builder $query) {
            if (Tenancy::check()) {
                $query->where($query->getModel()->qualifyColumn('gym_id'), Tenancy::id());
            }
        });

        static::creating(function ($model) {
            if (! $model->gym_id && Tenancy::check()) {
                $model->gym_id = Tenancy::id();
            }
            if (! $model->gym_id) {
                throw new \LogicException(class_basename($model).' cannot be created without a gym.');
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
