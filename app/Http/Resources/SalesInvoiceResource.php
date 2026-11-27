<?php

namespace App\Http\Resources;

use App\Filament\Support\Labels;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin SalesInvoice */
class SalesInvoiceResource extends JsonResource
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
            'customer' => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null,
            'status' => $this->status,
            'status_label' => __(Labels::STATUS[$this->status] ?? $this->status),
            'payment_status' => $this->payment_status,
            'payment_status_label' => __(Labels::PAYMENT_STATUS[$this->payment_status] ?? $this->payment_status),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount_amount,
            'tax' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'paid' => (float) $this->paid_amount,
            'remaining' => $this->remainingAmount(),
            'is_pos' => (bool) $this->is_pos,
            'items' => SalesInvoiceItemResource::collection($this->whenLoaded('items')),
            'print_url' => URL::temporarySignedRoute('print.signed', now()->addDays(7), [
                'type' => 'sales-invoice', 'id' => $this->id, 'lang' => app()->getLocale(),
            ]),
            'receipt_url' => URL::temporarySignedRoute('print.signed', now()->addDays(7), [
                'type' => 'sales-invoice', 'id' => $this->id, 'format' => 'thermal', 'lang' => app()->getLocale(),
            ]),
        ];
    }
}
