<?php

namespace App\Filament\Resources\DeliveryAreas;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\DeliveryAreas\Pages\ManageDeliveryAreas;
use App\Models\DeliveryArea;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class DeliveryAreaResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = DeliveryArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Delivery;

    public static function getModelLabel(): string
    {
        return __('منطقة توصيل');
    }

    public static function getPluralModelLabel(): string
    {
        return __('مناطق التوصيل');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('المنطقة'))->required(),
            TextInput::make('name_en')->label(__('الاسم بالإنجليزي')),
            TextInput::make('fee')->label(__('رسوم التوصيل'))->numeric()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->label(__('مفعلة'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('المنطقة'))->searchable(['name', 'name_en'])
                    ->description(fn (DeliveryArea $record) => $record->name_en),
                TextColumn::make('fee')->label(__('رسوم التوصيل'))->numeric(2)->sortable(),
                ToggleColumn::make('is_active')->label(__('مفعلة')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryAreas::route('/')];
    }
}
