<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;

/** خريطة الطيارين (للكاشير والمدير) */
class DriverTrackingController extends Controller
{
    public function __invoke()
    {
        return ['data' => self::payload()];
    }

    public static function payload(): array
    {
        return Driver::where('is_active', true)
            ->with(['activeOrders.customer', 'activeOrders.deliveryArea'])
            ->orderByDesc('is_online')->orderBy('name')
            ->get()
            ->map(fn (Driver $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'phone' => $d->phone,
                'is_online' => $d->is_online,
                'live' => $d->isLive(),
                'location' => $d->locationPayload(),
                'last_seen' => $d->location_updated_at?->diffForHumans(),
                'orders' => $d->activeOrders->map(fn ($o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status' => $o->status->value,
                    'stage' => $o->deliveryStage(),
                    'stage_label' => \App\Models\Order::deliveryStageLabel($o->deliveryStage()),
                    'customer' => $o->customer?->name,
                    'address' => $o->delivery_address,
                    'area' => $o->deliveryArea?->localized_name,
                    'lat' => $o->delivery_lat,
                    'lng' => $o->delivery_lng,
                    'total' => (float) $o->total,
                ])->values(),
            ])->all();
    }

    public function trail(Driver $driver)
    {
        return ['data' => $driver->locations()->where('created_at', '>=', now()->subHours(12))
            ->orderBy('id')->get(['latitude', 'longitude', 'created_at'])
            ->map(fn ($p) => ['lat' => $p->latitude, 'lng' => $p->longitude, 'at' => $p->created_at->toIso8601String()])];
    }
}
