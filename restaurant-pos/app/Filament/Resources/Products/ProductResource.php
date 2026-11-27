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

    protected static string|UnitEnum|null $navigationGroup = 'المنيو';

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'الأصناف';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('القسم')
                ->relationship('category', 'name')
                ->searchable()->preload()->required(),
            TextInput::make('name')->label('اسم الصنف')->required()->maxLength(255),
            TextInput::make('price')->label('سعر البيع')->numeric()->minValue(0)->required(),
            TextInput::make('cost')->label('التكلفة')->numeric()->minValue(0)->default(0),
            FileUpload::make('image')->label('الصورة')->image()->disk('public')->directory('products'),
            Toggle::make('is_active')->label('متاح للبيع')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular(),
                TextColumn::make('name')->label('الصنف')->searchable()->sortable(),
                TextColumn::make('category.name')->label('القسم')->sortable(),
                TextColumn::make('price')->label('السعر')->money(config('pos.currency_code', 'EGP'))->sortable(),
                TextColumn::make('cost')->label('التكلفة')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('متاح'),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('القسم')->relationship('category', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
