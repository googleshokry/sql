<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'number', 'type', 'status', 'source', 'user_id', 'restaurant_table_id', 'customer_id',
        'delivery_area_id', 'driver_id', 'delivery_address', 'subtotal', 'discount',
        'tax', 'service', 'delivery_fee', 'total', 'payment_method', 'is_paid',
        'paid_amount', 'change_amount', 'notes', 'paid_at',
    ];

    protected $casts = [
        'type' => OrderType::class,
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'service' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->number ??= static::nextNumber();
        });

        static::saved(function (Order $order) {
            $order->restaurantTable?->refreshStatus();
        });
    }

    /** رقم طلب يومي: 261127-0001 */
    public static function nextNumber(): string
    {
        $prefix = now()->format('ymd');
        $last = DB::table('orders')->where('number', 'like', $prefix.'-%')->lockForUpdate()->max('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurantTable()
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    /** إعادة حساب الإجماليات من الأصناف */
    public function recalculate(): void
    {
        $subtotal = (float) $this->items()->sum('total');

        $this->update(\App\Support\OrderTotals::calculate(
            $subtotal, (float) $this->discount, $this->type, (float) $this->delivery_fee,
        ));
    }

    public function markPaid(PaymentMethod $method, ?float $paidAmount = null): void
    {
        $paid = $paidAmount ?? (float) $this->total;

        $this->update([
            'is_paid' => true,
            'payment_method' => $method,
            'paid_amount' => $paid,
            'change_amount' => max(0, $paid - (float) $this->total),
            'paid_at' => now(),
            'status' => $this->type === OrderType::Delivery ? $this->status : OrderStatus::Completed,
        ]);
    }
}
