<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStock extends TableWidget
{
    protected static ?string $heading = 'أصناف وصلت لحد الطلب';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->lowStock()->withSum('stocks as qty', 'quantity'))
            ->paginated([5])
            ->columns([
                TextColumn::make('name')->label('الصنف'),
                TextColumn::make('qty')->label('الكمية')->numeric(3)->color('danger')->placeholder('0'),
                TextColumn::make('min_stock')->label('حد الطلب')->numeric(3),
            ])
            ->emptyStateHeading('لا توجد أصناف تحت حد الطلب 👍');
    }
}
