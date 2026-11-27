<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getModelLabel(): string
    {
        return __('app.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.plans');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name.ar')->label(__('app.name_ar'))->required(),
            TextInput::make('name.en')->label(__('app.name_en'))->required(),
            TextInput::make('max_children')->label(__('app.max_children'))->numeric()->minValue(1),
            TextInput::make('max_branches')->label(__('app.max_branches'))->numeric()->minValue(1),
            TextInput::make('price_monthly')->label(__('app.price_monthly'))->numeric()->required()->default(0),
            Toggle::make('is_active')->label(__('app.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name')),
                TextColumn::make('max_children')->label(__('app.max_children')),
                TextColumn::make('max_branches')->label(__('app.max_branches')),
                TextColumn::make('price_monthly')->label(__('app.price_monthly'))->money('EGP'),
                IconColumn::make('is_active')->label(__('app.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()->mutateRecordDataUsing(fn (array $data, Plan $record) => [...$data, 'name' => $record->getTranslations('name')]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
