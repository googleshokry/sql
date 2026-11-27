<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    protected $fillable = ['name', 'seats', 'status'];

    protected $casts = ['status' => TableStatus::class];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** إعادة حساب حالة الطاولة حسب الطلبات المفتوحة غير المدفوعة */
    public function refreshStatus(): void
    {
        $busy = $this->orders()
            ->whereIn('status', OrderStatus::open())
            ->where('is_paid', false)
            ->exists();

        $this->update(['status' => $busy ? TableStatus::Occupied : TableStatus::Available]);
    }
}
