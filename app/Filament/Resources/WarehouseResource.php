<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\ProductStock;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'مخزن';

    protected static ?string $pluralModelLabel = 'المخازن';

    protected static array $roles = ['storekeeper', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('اسم المخزن')->required(),
            Select::make('branch_id')->label('الفرع')->relationship('branch', 'name'),
            TextInput::make('address')->label('العنوان')->columnSpanFull(),
            Toggle::make('is_default')->label('المخزن الافتراضي'),
            Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('branch.name')->label('الفرع'),
                TextColumn::make('stock_value')->label('قيمة المخزون')->numeric(2)->state(fn ($record) => ProductStock::query()->where('warehouse_id', $record->id)->join('products', 'products.id', '=', 'product_stocks.product_id')->sum(\DB::raw('product_stocks.quantity * products.cost_price'))),
                IconColumn::make('is_default')->label('افتراضي')->boolean(),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWarehouses::route('/'),
        ];
    }
}
