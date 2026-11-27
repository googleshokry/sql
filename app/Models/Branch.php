<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
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
