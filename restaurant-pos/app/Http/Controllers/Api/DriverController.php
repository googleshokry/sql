<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

/** تطبيق الطيار */
class DriverController extends Controller
{
    private function driverId(Request $request): int
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403, __('الحساب غير مربوط بطيار'));

        return $driver->id;
    }

    public function orders(Request $request)
    {
        $driverId = $this->driverId($request);

        $orders = Order::with(['items.product', 'customer', 'deliveryArea'])
            ->where('driver_id', $driverId)
            ->where(fn ($q) => $q->whereIn('status', OrderStatus::open())
                ->orWhereDate('created_at', today()))
            ->latest('id')->get();

        $today = Order::where('driver_id', $driverId)->whereDate('created_at', today())
            ->where('status', OrderStatus::Completed);

        return [
            'data' => OrderResource::collection($orders),
            'summary' => [
                'delivered_today' => (clone $today)->count(),
                'cash_to_hand_over' => (float) (clone $today)->where('payment_method', PaymentMethod::Cash)->sum('total'),
            ],
        ];
    }

    public function delivered(Request $request, Order $order)
    {
        abort_unless($order->driver_id === $this->driverId($request), 404);
        abort_if($order->status === OrderStatus::Cancelled, 422, __('الطلب ملغي'));

        if (! $order->is_paid) {
            $order->markPaid(PaymentMethod::Cash); // الطيار حصّل كاش من العميل
        }
        $order->update(['status' => OrderStatus::Completed]);

        return new OrderResource($order->fresh(['items.product', 'customer', 'deliveryArea']));
    }

    public function pickup(Request $request, Order $order)
    {
        abort_unless($order->driver_id === $this->driverId($request), 404);
        $order->update(['status' => OrderStatus::OutForDelivery]);

        return new OrderResource($order->fresh(['items.product', 'customer', 'deliveryArea']));
    }
}
