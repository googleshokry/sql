<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/** العميل يقدر يسجل دخول من تطبيق العملاء */
class Customer extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'phone', 'phone2', 'password', 'delivery_area_id', 'address', 'notes'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed'];

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
