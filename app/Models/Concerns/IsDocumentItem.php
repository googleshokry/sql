<?php

namespace App\Models\Concerns;

use App\Models\Product;
use App\Support\DocumentTotals;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait IsDocumentItem
{
    public static function bootIsDocumentItem(): void
    {
        static::saving(function ($item) {
            $line = DocumentTotals::line($item->only(['quantity', 'unit_price', 'discount_percent', 'tax_rate']));
            $item->total = $line['total'];
            $item->tax_amount = $line['tax_amount'];
            if (blank($item->description) && $item->product_id) {
                $item->description = $item->product?->name;
            }
        });

        static::saved(fn ($item) => $item->document?->recalculate());
        static::deleted(fn ($item) => $item->document?->recalculate());
    }

    public function initializeIsDocumentItem(): void
    {
        $this->mergeCasts([
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ]);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(static::DOCUMENT_MODEL, str(class_basename(static::DOCUMENT_MODEL))->snake()->append('_id')->toString());
    }

    /** صافي السطر بعد خصم المستند (للتكلفة والمخزون) */
    public function netUnitPrice(): float
    {
        $doc = $this->document;
        $ratio = ($doc && (float) $doc->subtotal > 0) ? 1 - ((float) $doc->discount_amount / (float) $doc->subtotal) : 1;
        $qty = (float) $this->quantity ?: 1;

        return round(((float) $this->total / $qty) * $ratio, 4);
    }
}
