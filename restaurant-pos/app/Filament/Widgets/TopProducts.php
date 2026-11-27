<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopProducts extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'الأكثر مبيعاً اليوم';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => OrderItem::query()
                ->select('product_name', DB::raw('MIN(order_items.id) as id'), DB::raw('SUM(quantity) as qty'), DB::raw('SUM(order_items.total) as amount'))
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereDate('orders.created_at', today())
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->groupBy('product_name')
                ->orderByDesc('qty'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('product_name')->label('الصنف'),
                TextColumn::make('qty')->label('الكمية'),
                TextColumn::make('amount')->label('القيمة')->numeric(2),
            ]);
    }
}
