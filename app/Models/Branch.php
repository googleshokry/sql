<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function treasuries(): HasMany
    {
        return $this->hasMany(Treasury::class);
    }
}
