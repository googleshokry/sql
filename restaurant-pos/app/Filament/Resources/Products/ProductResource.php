<?php

namespace App\Filament\Resources\Products;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Menu;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('صنف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الأصناف');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label(__('القسم'))
                ->relationship('category', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->localized_name)
                ->searchable()->preload()->required(),
            TextInput::make('name')->label(__('اسم الصنف'))->required()->maxLength(255),
            TextInput::make('name_en')->label(__('الاسم بالإنجليزي'))->maxLength(255),
            TextInput::make('price')->label(__('سعر البيع'))->numeric()->minValue(0)->required(),
            TextInput::make('cost')->label(__('التكلفة'))->numeric()->minValue(0)->default(0),
            FileUpload::make('image')->label(__('الصورة'))->image()->disk('public')->directory('products'),
            Toggle::make('is_active')->label(__('متاح للبيع'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular(),
                TextColumn::make('name')->label(__('الصنف'))->searchable(['name', 'name_en'])->sortable()
                    ->description(fn (Product $record) => $record->name_en),
                TextColumn::make('category.name')->label(__('القسم'))->sortable(),
                TextColumn::make('price')->label(__('السعر'))->money(config('pos.currency_code', 'EGP'))->sortable(),
                TextColumn::make('cost')->label(__('التكلفة'))->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label(__('متاح')),
            ])
            ->filters([
                SelectFilter::make('category_id')->label(__('القسم'))->relationship('category', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
