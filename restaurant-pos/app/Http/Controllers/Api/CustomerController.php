<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** تطبيق العملاء */
class CustomerController extends Controller
{
    public function updateProfile(Request $request)
    {
        $customer = $request->user();
        $customer->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
            'password' => ['sometimes', 'string', 'min:6'],
        ]));

        return ['user' => AuthController::customerPayload($customer)];
    }

    public function orders(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items.product', 'deliveryArea', 'driver'])->latest('id')->paginate(20)
        );
    }

    public function show(Request $request, int $id)
    {
        return new OrderResource($request->user()->orders()->with(['items.product', 'deliveryArea', 'driver'])->findOrFail($id));
    }

    public function store(Request $request, OrderService $service)
    {
        $customer = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(['takeaway', 'delivery'])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'delivery_area_id' => ['nullable', 'required_if:type,delivery', 'exists:delivery_areas,id'],
            'address' => ['nullable', 'required_if:type,delivery', 'string', 'max:1000'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'wallet'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $service->place([
            'type' => $data['type'],
            'items' => $data['items'],
            'source' => 'customer_app',
            'status' => OrderStatus::Pending, // بانتظار تأكيد المطعم
            'customer_id' => $customer->id,
            'delivery_area_id' => $data['delivery_area_id'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => trim(($data['notes'] ?? '').(($data['payment_method'] ?? 'cash') !== 'cash'
                ? __(' | الدفع: ').$data['payment_method'] : '')) ?: null,
            'pay' => false,
        ]);

        // تحديث عنوان العميل لآخر عنوان
        if ($data['type'] === 'delivery') {
            $customer->update(['address' => $data['address'], 'delivery_area_id' => $data['delivery_area_id']]);
        }

        return (new OrderResource($order->load(['items.product', 'deliveryArea'])))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, int $id)
    {
        $order = $request->user()->orders()->findOrFail($id);
        abort_unless($order->status === OrderStatus::Pending, 422, __('لا يمكن إلغاء الطلب بعد بدء التحضير'));
        $order->update(['status' => OrderStatus::Cancelled]);

        return new OrderResource($order->fresh(['items.product']));
    }
}
