<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** تطبيق الطيار */
class DriverController extends Controller
{
    private const RELATIONS = ['items.product', 'customer', 'deliveryArea'];

    private function driver(Request $request): Driver
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403, __('الحساب غير مربوط بطيار'));

        return $driver;
    }

    private function ownOrder(Request $request, Order $order): Driver
    {
        $driver = $this->driver($request);
        abort_unless($order->driver_id === $driver->id, 404);
        abort_if($order->status === OrderStatus::Cancelled, 422, __('الطلب ملغي'));

        return $driver;
    }

    public function orders(Request $request)
    {
        $driver = $this->driver($request);

        $orders = Order::with(self::RELATIONS)
            ->where('driver_id', $driver->id)
            ->where(fn ($q) => $q->whereIn('status', OrderStatus::open())
                ->orWhereDate('created_at', today()))
            ->latest('id')->get();

        $today = Order::where('driver_id', $driver->id)->whereDate('created_at', today())
            ->where('status', OrderStatus::Completed);

        return [
            'data' => OrderResource::collection($orders),
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'is_online' => $driver->is_online,
                'location' => $driver->locationPayload(),
            ],
            'summary' => [
                'delivered_today' => (clone $today)->count(),
                'cash_to_hand_over' => (float) (clone $today)->where('payment_method', PaymentMethod::Cash)->sum('total'),
            ],
        ];
    }

    /** بدء / إنهاء الشيفت */
    public function shift(Request $request)
    {
        $data = $request->validate(['online' => ['required', 'boolean']]);
        $driver = $this->driver($request);
        $driver->update(['is_online' => $data['online']]);

        return ['is_online' => $driver->is_online];
    }

    /** الموبايل بيبعت موقعه الحالي كل شوية وهو في الشيفت */
    public function location(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'speed' => ['nullable', 'numeric'],
        ]);

        $driver = $this->driver($request);
        $driver->recordLocation((float) $data['lat'], (float) $data['lng'], $data['accuracy'] ?? null, $data['speed'] ?? null);

        return ['ok' => true, 'location' => $driver->locationPayload()];
    }

    public function pickup(Request $request, Order $order)
    {
        $this->ownOrder($request, $order);
        $order->update(['status' => OrderStatus::OutForDelivery, 'picked_up_at' => $order->picked_up_at ?? now()]);

        return new OrderResource($order->fresh(self::RELATIONS));
    }

    public function arrived(Request $request, Order $order)
    {
        $this->ownOrder($request, $order);
        $order->update([
            'status' => OrderStatus::OutForDelivery,
            'picked_up_at' => $order->picked_up_at ?? now(),
            'arrived_at' => now(),
        ]);

        return new OrderResource($order->fresh(self::RELATIONS));
    }

    public function delivered(Request $request, Order $order)
    {
        $this->ownOrder($request, $order);

        if (! $order->is_paid) {
            $order->markPaid(PaymentMethod::Cash); // الطيار حصّل كاش من العميل
        }
        $order->update([
            'status' => OrderStatus::Completed,
            'picked_up_at' => $order->picked_up_at ?? now(),
            'delivered_at' => now(),
        ]);

        return new OrderResource($order->fresh(self::RELATIONS));
    }

    /** تعذر التسليم: الطلب يرجع للمطعم (جاهز وبدون طيار) مع السبب */
    public function failed(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->ownOrder($request, $order);
        if ($order->status === OrderStatus::Completed) {
            throw ValidationException::withMessages(['order' => __('الطلب تم تسليمه بالفعل')]);
        }

        $order->update([
            'status' => OrderStatus::Ready,
            'driver_id' => null,
            'picked_up_at' => null,
            'arrived_at' => null,
            'driver_note' => __('تعذر التسليم').': '.$data['reason'],
        ]);

        return response()->noContent();
    }
}
