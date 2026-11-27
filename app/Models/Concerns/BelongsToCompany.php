<?php

namespace App\Models\Concerns;

use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolates a model's rows per company (tenant).
 *
 * Every query is limited to the current company; without a company nothing is returned.
 * New rows are stamped with the current company automatically.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (Tenancy::isBypassed()) {
                return;
            }

            $companyId = Tenancy::id();
            $column = $builder->getModel()->qualifyColumn('company_id');

            $companyId === null ? $builder->whereRaw('1 = 0') : $builder->where($column, $companyId);
        });

        static::creating(function ($model) {
            if ($model->company_id) {
                return;
            }

            $model->company_id = Tenancy::id()
                ?? throw new BusinessException('No active company: cannot create '.class_basename($model).' outside a tenant context.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
