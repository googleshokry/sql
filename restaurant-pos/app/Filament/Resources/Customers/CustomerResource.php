<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = \App\Filament\NavGroup::Delivery;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('عميل');
    }

    public static function getPluralModelLabel(): string
    {
        return __('العملاء');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('الاسم'))->required(),
            TextInput::make('phone')->label(__('الموبايل'))->tel()->required()->unique(ignoreRecord: true),
            TextInput::make('phone2')->label(__('موبايل آخر'))->tel(),
            Select::make('delivery_area_id')->label(__('المنطقة'))->relationship('deliveryArea', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->localized_name)->preload(),
            Textarea::make('address')->label(__('العنوان'))->columnSpanFull(),
            Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable(),
                TextColumn::make('phone')->label(__('الموبايل'))->searchable(),
                TextColumn::make('deliveryArea.name')->label(__('المنطقة')),
                TextColumn::make('address')->label(__('العنوان'))->limit(40),
                TextColumn::make('orders_count')->label(__('عدد الطلبات'))->counts('orders')->sortable(),
                TextColumn::make('orders_sum_total')->label(__('إجمالي المشتريات'))->sum('orders', 'total')->numeric(2)->sortable(),
            ])
            ->filters([
                SelectFilter::make('delivery_area_id')->label(__('المنطقة'))->relationship('deliveryArea', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn () => auth()->user()->isAdmin())]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
