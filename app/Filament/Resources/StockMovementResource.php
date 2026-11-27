<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Services\Inventory;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** سجل حركة الأصناف (للعرض فقط) */
class StockMovementResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'حركة';

    protected static ?string $pluralModelLabel = 'حركة الأصناف';

    protected static array $roles = ['storekeeper', 'manager', 'accountant'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('product.name')->label('الصنف')->searchable(),
                TextColumn::make('warehouse.name')->label('المخزن'),
                TextColumn::make('type')->label('نوع الحركة')->badge()->formatStateUsing(fn ($state) => Inventory::TYPES[$state] ?? $state),
                TextColumn::make('reference_number')->label('المستند')->state(fn ($record) => $record->reference?->number ?? '-'),
                TextColumn::make('quantity')->label('الكمية')->numeric(3)->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('unit_cost')->label('التكلفة')->numeric(2),
                TextColumn::make('balance_after')->label('الرصيد بعد الحركة')->numeric(3),
            ])
            ->filters([
                SelectFilter::make('product_id')->label('الصنف')->relationship('product', 'name')->searchable()->preload(),
                SelectFilter::make('warehouse_id')->label('المخزن')->relationship('warehouse', 'name'),
                SelectFilter::make('type')->label('النوع')->options(Inventory::TYPES),
                Filter::make('date')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('to')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
