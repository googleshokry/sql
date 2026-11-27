<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

class CategoryResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Menu;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('قسم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الأقسام');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('اسم القسم'))->required()->maxLength(255),
            TextInput::make('name_en')->label(__('الاسم بالإنجليزي'))->maxLength(255),
            TextInput::make('sort')->label(__('الترتيب'))->numeric()->default(0),
            Toggle::make('is_active')->label(__('مفعل'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label(__('القسم'))->searchable(['name', 'name_en'])
                    ->description(fn (Category $record) => $record->name_en),
                TextColumn::make('products_count')->label(__('عدد الأصناف'))->counts('products'),
                ToggleColumn::make('is_active')->label(__('مفعل')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
