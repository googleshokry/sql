<?php

namespace App\Filament\Resources\Drivers;

use App\Enums\OrderType;
use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\Driver;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'الدليفري';

    protected static ?string $modelLabel = 'طيار';

    protected static ?string $pluralModelLabel = 'الطيارين';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required(),
            TextInput::make('phone')->label('الموبايل')->tel(),
            Toggle::make('is_active')->label('متاح')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['orders as today_orders_count' => fn ($q) => $q
                    ->where('type', OrderType::Delivery)->whereDate('created_at', today())])
                ->withSum(['orders as today_orders_total' => fn ($q) => $q
                    ->where('type', OrderType::Delivery)->whereDate('created_at', today())], 'total'))
            ->columns([
                TextColumn::make('name')->label('الطيار')->searchable(),
                TextColumn::make('phone')->label('الموبايل'),
                TextColumn::make('today_orders_count')->label('طلبات اليوم'),
                TextColumn::make('today_orders_total')->label('تحصيل اليوم')->numeric(2)->default(0),
                ToggleColumn::make('is_active')->label('متاح'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn () => auth()->user()->isAdmin())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDrivers::route('/')];
    }
}
