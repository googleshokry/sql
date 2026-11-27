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
        'delivery_lat', 'delivery_lng', 'picked_up_at', 'arrived_at', 'delivered_at', 'driver_note',
    ];

    protected $casts = [
        'type' => OrderType::class,
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
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

    /**
     * مرحلة التوصيل للطيار: assigned → picked_up → arrived → delivered
     */
    public function deliveryStage(): ?string
    {
        if ($this->type !== OrderType::Delivery) {
            return null;
        }

        return match (true) {
            $this->status === OrderStatus::Cancelled => 'cancelled',
            $this->delivered_at !== null || $this->status === OrderStatus::Completed => 'delivered',
            $this->arrived_at !== null => 'arrived',
            $this->picked_up_at !== null => 'picked_up',
            $this->driver_id !== null => 'assigned',
            default => 'unassigned',
        };
    }

    public static function deliveryStageLabel(?string $stage): ?string
    {
        return match ($stage) {
            'unassigned' => __('بدون طيار'),
            'assigned' => __('في انتظار الاستلام'),
            'picked_up' => __('في الطريق'),
            'arrived' => __('وصل للعميل'),
            'delivered' => __('تم التسليم'),
            'cancelled' => __('ملغي'),
            default => null,
        };
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
