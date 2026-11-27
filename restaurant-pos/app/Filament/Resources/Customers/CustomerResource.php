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

    protected static string|UnitEnum|null $navigationGroup = 'الدليفري';

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required(),
            TextInput::make('phone')->label('الموبايل')->tel()->required()->unique(ignoreRecord: true),
            TextInput::make('phone2')->label('موبايل آخر')->tel(),
            Select::make('delivery_area_id')->label('المنطقة')->relationship('deliveryArea', 'name')->preload(),
            Textarea::make('address')->label('العنوان')->columnSpanFull(),
            Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('deliveryArea.name')->label('المنطقة'),
                TextColumn::make('address')->label('العنوان')->limit(40),
                TextColumn::make('orders_count')->label('عدد الطلبات')->counts('orders')->sortable(),
                TextColumn::make('orders_sum_total')->label('إجمالي المشتريات')->sum('orders', 'total')->numeric(2)->sortable(),
            ])
            ->filters([
                SelectFilter::make('delivery_area_id')->label('المنطقة')->relationship('deliveryArea', 'name'),
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
