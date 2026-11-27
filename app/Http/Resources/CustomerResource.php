<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'tax_number' => $this->tax_number,
            'city' => $this->city,
            'address' => $this->address,
            'price_list' => $this->price_list,
            'credit_limit' => (float) $this->credit_limit,
            'balance' => $this->balance(),
        ];
    }
}
