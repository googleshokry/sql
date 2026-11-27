<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category?->name,
            'unit' => $this->unit?->name,
            'sale_price' => (float) $this->sale_price,
            'wholesale_price' => (float) $this->wholesale_price,
            'price' => $this->priceFor($request->query('price_list')),
            'tax_rate' => (float) $this->tax_rate,
            'track_stock' => $this->track_stock,
            'quantity' => $this->track_stock ? (float) ($this->stock_qty ?? $this->quantity()) : null,
            'min_stock' => (float) $this->min_stock,
            'image_url' => $this->image ? asset('storage/'.$this->image) : null,
        ];
    }
}
