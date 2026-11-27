<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (SaleItem $i) {
            $i->total = round($i->quantity * $i->unit_price, 2);
        });
        static::created(fn (SaleItem $i) => Product::whereKey($i->product_id)->decrement('stock', $i->quantity));
        static::updated(function (SaleItem $i) {
            if ($i->wasChanged(['quantity', 'product_id'])) {
                Product::whereKey($i->getOriginal('product_id'))->increment('stock', $i->getOriginal('quantity'));
                Product::whereKey($i->product_id)->decrement('stock', $i->quantity);
            }
        });
        static::deleted(fn (SaleItem $i) => Product::whereKey($i->product_id)->increment('stock', $i->quantity));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
