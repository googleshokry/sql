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

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Delivery;

    public static function getModelLabel(): string
    {
        return __('طيار');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الطيارين');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('الاسم'))->required(),
            TextInput::make('phone')->label(__('الموبايل'))->tel(),
            \Filament\Forms\Components\Select::make('user_id')->label(__('حساب الدخول على تطبيق الطيار'))
                ->relationship('user', 'email', fn (Builder $query) => $query->where('role', \App\Enums\UserRole::Driver))
                ->helperText(__('أنشئ مستخدم بصلاحية "طيار" من صفحة المستخدمين ثم اربطه هنا'))
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')->label(__('متاح'))->default(true),
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
                TextColumn::make('name')->label(__('الطيار'))->searchable(),
                TextColumn::make('phone')->label(__('الموبايل')),
                TextColumn::make('today_orders_count')->label(__('طلبات اليوم')),
                TextColumn::make('today_orders_total')->label(__('تحصيل اليوم'))->numeric(2)->default(0),
                ToggleColumn::make('is_active')->label(__('متاح')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn () => auth()->user()->isAdmin())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDrivers::route('/')];
    }
}
