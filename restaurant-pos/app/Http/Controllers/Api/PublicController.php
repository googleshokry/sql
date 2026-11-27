<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\DeliveryArea;

class PublicController extends Controller
{
    public function settings()
    {
        return [
            'restaurant_name' => config('pos.restaurant_name'),
            'restaurant_phone' => config('pos.restaurant_phone'),
            'restaurant_address' => config('pos.restaurant_address'),
            'currency' => config('pos.currency'),
            'tax_rate' => (float) config('pos.tax_rate'),
            'service_rate' => (float) config('pos.service_rate'),
        ];
    }

    public function menu()
    {
        $categories = Category::where('is_active', true)->orderBy('sort')
            ->with(['products' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->get();

        return [
            'data' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'products' => ProductResource::collection($c->products),
            ]),
        ];
    }

    public function deliveryAreas()
    {
        return ['data' => DeliveryArea::where('is_active', true)->orderBy('name')->get(['id', 'name', 'fee'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'fee' => (float) $a->fee])];
    }
}
