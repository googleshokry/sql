<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = ExpenseCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Finance;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('بند مصروف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('بنود المصروفات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('اسم البند'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('البند'))->searchable(),
                TextColumn::make('expenses_count')->label(__('عدد المصروفات'))->counts('expenses'),
                TextColumn::make('expenses_sum_amount')->label(__('الإجمالي'))->sum('expenses', 'amount')->numeric(2, locale: 'en'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExpenseCategories::route('/')];
    }
}
