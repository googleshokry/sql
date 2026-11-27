<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date?->toDateString(),
            'category' => $this->category?->name,
            'treasury' => $this->treasury?->name,
            'amount' => (float) $this->amount,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'description' => $this->description,
        ];
    }
}
