<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** إدارة الطلبات (كاشير / مدير) */
class OrderController extends Controller
{
    private const RELATIONS = ['items.product', 'customer', 'restaurantTable', 'driver', 'deliveryArea'];

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'open');

        $orders = Order::with(['customer', 'restaurantTable', 'driver'])
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', OrderStatus::open()))
            ->when($filter === 'today', fn ($q) => $q->whereDate('created_at', today()))
            ->when($filter === 'unpaid', fn ($q) => $q->where('is_paid', false)->where('status', '!=', OrderStatus::Cancelled))
            ->when($filter === 'app', fn ($q) => $q->where('source', 'customer_app')->whereIn('status', OrderStatus::open()))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%$s%")
                ->orWhereHas('customer', fn ($q) => $q->where('phone', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))))
            ->latest('id')
            ->paginate(30);

        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        return new OrderResource($order->load(self::RELATIONS));
    }

    public function pay(Request $request, Order $order)
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'card', 'wallet'])],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($order->is_paid || $order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['order' => __('الطلب مدفوع أو ملغي')]);
        }
        if (isset($data['paid_amount']) && (float) $data['paid_amount'] < (float) $order->total) {
            throw ValidationException::withMessages(['paid_amount' => __('المبلغ المدفوع أقل من الإجمالي')]);
        }

        $order->markPaid(PaymentMethod::from($data['payment_method']), isset($data['paid_amount']) ? (float) $data['paid_amount'] : null);

        return new OrderResource($order->fresh(self::RELATIONS));
    }

    public function status(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);

        if ($data['status'] === OrderStatus::Cancelled->value && ! $request->user()->isAdmin()) {
            abort(403, __('الإلغاء للمدير فقط'));
        }

        $order->update(['status' => $data['status']]);

        return new OrderResource($order->fresh(self::RELATIONS));
    }

    public function assignDriver(Request $request, Order $order)
    {
        $data = $request->validate(['driver_id' => ['required', 'exists:drivers,id']]);
        abort_unless($order->type === OrderType::Delivery, 422, __('الطلب ليس دليفري'));

        $order->update(['driver_id' => $data['driver_id'], 'status' => OrderStatus::OutForDelivery]);

        return new OrderResource($order->fresh(self::RELATIONS));
    }
}
