<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    protected $fillable = ['name', 'fee', 'is_active'];

    protected $casts = ['fee' => 'decimal:2', 'is_active' => 'boolean'];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
