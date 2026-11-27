<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\ManagePlans;
use App\Models\Central\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('باقة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الباقات');
    }

    public static function form(Schema $schema): Schema
    {
        $limit = fn (string $field, string $label) => TextInput::make($field)->label($label)->numeric()->minValue(0)
            ->placeholder(__('فاضي = بلا حدود'));

        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('الاسم'))->required(),
            TextInput::make('name_en')->label(__('الاسم بالإنجليزي')),
            TextInput::make('price_monthly')->label(__('السعر الشهري'))->numeric()->required()->minValue(0),
            TextInput::make('price_yearly')->label(__('السعر السنوي'))->numeric()->minValue(0)->default(0),
            $limit('max_users', __('أقصى عدد مستخدمين')),
            $limit('max_drivers', __('أقصى عدد طيارين')),
            $limit('max_products', __('أقصى عدد أصناف')),
            $limit('max_orders_per_month', __('أقصى طلبات شهرياً')),
            TextInput::make('sort')->label(__('الترتيب'))->numeric()->default(0),
            Toggle::make('customer_app')->label(__('تطبيق العملاء'))->default(true),
            Toggle::make('is_active')->label(__('متاحة للاشتراك'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $unlimited = fn ($state) => $state ?? '∞';

        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label(__('الباقة'))->description(fn (Plan $r) => $r->name_en),
                TextColumn::make('price_monthly')->label(__('شهري'))->numeric(0),
                TextColumn::make('price_yearly')->label(__('سنوي'))->numeric(0),
                TextColumn::make('max_users')->label(__('مستخدمين'))->formatStateUsing($unlimited)->default('∞'),
                TextColumn::make('max_drivers')->label(__('طيارين'))->formatStateUsing($unlimited)->default('∞'),
                TextColumn::make('max_orders_per_month')->label(__('طلبات/شهر'))->formatStateUsing($unlimited)->default('∞'),
                IconColumn::make('customer_app')->label(__('تطبيق العملاء'))->boolean(),
                TextColumn::make('tenants_count')->label(__('المطاعم'))->counts('tenants'),
                ToggleColumn::make('is_active')->label(__('متاحة')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
