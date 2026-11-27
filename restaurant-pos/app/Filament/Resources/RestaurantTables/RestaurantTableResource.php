<?php

namespace App\Filament\Resources\RestaurantTables;

use App\Enums\TableStatus;
use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\RestaurantTables\Pages\ManageRestaurantTables;
use App\Models\RestaurantTable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RestaurantTableResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = RestaurantTable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Settings;

    public static function getModelLabel(): string
    {
        return __('طاولة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الطاولات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('اسم / رقم الطاولة'))->required(),
            TextInput::make('seats')->label(__('عدد الكراسي'))->numeric()->default(4),
            Select::make('status')->label(__('الحالة'))->options(TableStatus::class)->default(TableStatus::Available)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الطاولة'))->searchable(),
                TextColumn::make('seats')->label(__('الكراسي')),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRestaurantTables::route('/')];
    }
}
