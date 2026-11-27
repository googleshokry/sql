<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'direction' => $this->direction,
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'method_label' => __(Payment::METHODS[$this->method] ?? $this->method),
            'treasury' => $this->treasury?->name,
            'party' => $this->party?->name,
            'invoice' => $this->payable?->number,
            'notes' => $this->notes,
            'print_url' => URL::temporarySignedRoute('print.signed', now()->addDays(7), [
                'type' => 'payment', 'id' => $this->id, 'lang' => app()->getLocale(), 'company' => $this->company_id,
            ]),
        ];
    }
}
