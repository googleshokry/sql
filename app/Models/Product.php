<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = [
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'price_includes_tax' => 'boolean',
        'purchase_price' => 'decimal:2',
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'min_sale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_stock' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $p) {
            if (blank($p->sku)) {
                $next = static::query()->count() + 1;
                do {
                    $p->sku = 'P'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
                } while (static::query()->where('sku', $p->sku)->exists());
            }
            if ($p->type === 'service') {
                $p->track_stock = false;
            }
            if ((float) $p->cost_price <= 0) {
                $p->cost_price = $p->purchase_price ?? 0;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function quantity(?int $warehouseId = null): float
    {
        return (float) $this->stocks()->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))->sum('quantity');
    }

    /** سعر البيع قبل الضريبة حسب قائمة الأسعار */
    public function priceFor(?string $priceList = 'retail'): float
    {
        $price = $priceList === 'wholesale' && (float) $this->wholesale_price > 0 ? (float) $this->wholesale_price : (float) $this->sale_price;

        return $this->price_includes_tax ? round($price / (1 + (float) $this->tax_rate / 100), 2) : $price;
    }

    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereRaw('(select coalesce(sum(quantity),0) from product_stocks where product_stocks.product_id = products.id) <= products.min_stock');
    }
}
