<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->localized_name,
            'name_ar' => $this->name,
            'name_en' => $this->name_en,
            'price' => (float) $this->price,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
        ];
    }
}
