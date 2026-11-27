<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** تطبيق الكاشير */
class PosController extends Controller
{
    public function tables(OrderService $service)
    {
        return ['data' => RestaurantTable::orderBy('id')->get()->map(function ($t) use ($service) {
            $open = $service->openTableOrder($t->id);

            return [
                'id' => $t->id,
                'name' => $t->name,
                'seats' => $t->seats,
                'status' => $t->status->value,
                'open_order' => $open ? ['id' => $open->id, 'number' => $open->number, 'total' => (float) $open->total] : null,
            ];
        })];
    }

    public function drivers()
    {
        return ['data' => Driver::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone'])];
    }

    public function lookupCustomer(Request $request)
    {
        $phone = trim((string) $request->query('phone'));
        $c = $phone === '' ? null : Customer::where('phone', $phone)->orWhere('phone2', $phone)->first();

        return ['data' => $c ? AuthController::customerPayload($c) : null];
    }

    public function store(Request $request, OrderService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'takeaway', 'delivery'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'table_id' => ['nullable', 'exists:restaurant_tables,id'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'driver_id' => ['nullable', 'exists:drivers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'pay' => ['boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'wallet'])],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = $service->place([...$data, 'source' => 'staff_app', 'user_id' => $request->user()->id]);

        return (new OrderResource($order->load(['items', 'customer', 'restaurantTable', 'driver'])))
            ->response()->setStatusCode(201);
    }
}
