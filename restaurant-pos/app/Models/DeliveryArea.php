<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    use HasLocalizedName;

    protected $fillable = ['name', 'name_en', 'fee', 'is_active'];

    protected $casts = ['fee' => 'decimal:2', 'is_active' => 'boolean'];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
