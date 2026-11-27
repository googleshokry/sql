<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'phone2', 'delivery_area_id', 'address', 'notes'];

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
