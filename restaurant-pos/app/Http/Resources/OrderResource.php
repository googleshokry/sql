<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'source' => $this->source,
            'table' => $this->whenLoaded('restaurantTable', fn () => $this->restaurantTable ? [
                'id' => $this->restaurantTable->id, 'name' => $this->restaurantTable->name,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id, 'name' => $this->driver->name, 'phone' => $this->driver->phone,
            ] : null),
            'delivery_area' => $this->whenLoaded('deliveryArea', fn () => $this->deliveryArea?->localized_name),
            'delivery_address' => $this->delivery_address,
            'delivery_location' => $this->delivery_lat !== null ? ['lat' => $this->delivery_lat, 'lng' => $this->delivery_lng] : null,
            'delivery_stage' => $this->deliveryStage(),
            'delivery_stage_label' => Order::deliveryStageLabel($this->deliveryStage()),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'driver_note' => $this->driver_note,
            // موقع الطيار يظهر فقط والطلب في الطريق
            'driver_location' => $this->when(
                $this->relationLoaded('driver') && $this->driver && $this->status === OrderStatus::OutForDelivery,
                fn () => $this->driver->locationPayload(),
            ),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'service' => (float) $this->service,
            'tax' => (float) $this->tax,
            'delivery_fee' => (float) $this->delivery_fee,
            'total' => (float) $this->total,
            'is_paid' => $this->is_paid,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->getLabel(),
            'paid_amount' => (float) $this->paid_amount,
            'change_amount' => (float) $this->change_amount,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->relationLoaded('product') && $i->product ? $i->product->localized_name : $i->product_name,
                'price' => (float) $i->price,
                'qty' => $i->quantity,
                'total' => (float) $i->total,
                'notes' => $i->notes,
            ])),
            'receipt_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('orders.receipt.signed', now()->addDay(), ['order' => $this->id, 'lang' => app()->getLocale()]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
