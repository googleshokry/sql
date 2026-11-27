<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

class Inventory
{
    public const TYPES = [
        'opening' => 'رصيد افتتاحي',
        'purchase' => 'مشتريات',
        'purchase_return' => 'مرتجع مشتريات',
        'sale' => 'مبيعات',
        'sale_return' => 'مرتجع مبيعات',
        'transfer_in' => 'تحويل وارد',
        'transfer_out' => 'تحويل صادر',
        'adjustment' => 'تسوية جرد',
    ];

    /**
     * حركة مخزنية. الكمية موجبة للوارد وسالبة للصادر.
     * الوارد بتكلفة (مشتريات/افتتاحي) يحدّث متوسط التكلفة المرجّح.
     */
    public static function move(Product $product, int $warehouseId, float $qty, string $type, float $unitCost, Model $reference, $date, ?string $notes = null): ?StockMovement
    {
        if (! $product->track_stock || $qty == 0) {
            return null;
        }

        $stock = ProductStock::query()->lockForUpdate()->firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
            ['quantity' => 0],
        );

        if ($qty < 0 && ! Setting::get('allow_negative_stock') && (float) $stock->quantity + $qty < -0.0001) {
            throw new BusinessException(__('الكمية المتاحة من [:product] غير كافية في المخزن (المتاح: :available)', ['product' => $product->name, 'available' => (float) $stock->quantity]));
        }

        if ($qty > 0 && in_array($type, ['purchase', 'opening'])) {
            $totalQty = $product->quantity();
            $newQty = $totalQty + $qty;
            $product->cost_price = $newQty > 0 && $totalQty > 0
                ? round((($totalQty * (float) $product->cost_price) + ($qty * $unitCost)) / $newQty, 4)
                : $unitCost;
            if ($type === 'purchase') {
                $product->purchase_price = $unitCost;
            }
            $product->saveQuietly();
        }

        $stock->quantity = (float) $stock->quantity + $qty;
        $stock->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'date' => $date,
            'type' => $type,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'balance_after' => $stock->quantity,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'notes' => $notes,
        ]);
    }

    /** عكس كل الحركات المخزنية المرتبطة بمستند */
    public static function reverse(Model $reference): void
    {
        StockMovement::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->get()
            ->each(function (StockMovement $m) {
                ProductStock::query()
                    ->where('product_id', $m->product_id)
                    ->where('warehouse_id', $m->warehouse_id)
                    ->decrement('quantity', $m->quantity);
                $m->delete();
            });
    }
}
