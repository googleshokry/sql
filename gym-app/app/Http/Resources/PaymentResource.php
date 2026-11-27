<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'method_label' => Payment::methods()[$this->method] ?? $this->method,
            'type' => $this->type,
            'type_label' => Payment::types()[$this->type] ?? $this->type,
            'plan' => $this->subscription?->plan?->name,
            'paid_at' => $this->paid_at->toIso8601String(),
        ];
    }
}
