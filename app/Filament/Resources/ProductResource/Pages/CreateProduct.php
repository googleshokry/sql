<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\Accounting;
use App\Services\Inventory;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** رصيد أول المدة: حركة مخزنية + قيد (مخزون / أرصدة افتتاحية) */
    protected function afterCreate(): void
    {
        $qty = (float) ($this->data['opening_quantity'] ?? 0);
        $warehouse = $this->data['opening_warehouse_id'] ?? null;
        $product = $this->record;

        if ($qty > 0 && $warehouse && $product->track_stock) {
            $cost = (float) $product->purchase_price;
            Inventory::move($product, (int) $warehouse, $qty, 'opening', $cost, $product, now()->toDateString(), 'رصيد أول المدة');
            Accounting::sync($product, now()->toDateString(), 'رصيد أول المدة - '.$product->name, [
                ['account_id' => Accounting::account('inventory')->id, 'debit' => round($qty * $cost, 2)],
                ['account_id' => Accounting::account('opening_equity')->id, 'credit' => round($qty * $cost, 2)],
            ]);
        }
    }
}
