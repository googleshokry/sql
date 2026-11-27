<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\StockTransferResource\Pages;
use App\Filament\Support\Labels;
use App\Models\Product;
use App\Models\StockTransfer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as EntryColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockTransferResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'تحويل مخزني';

    protected static ?string $pluralModelLabel = 'التحويلات المخزنية';

    protected static array $roles = ['storekeeper', 'manager'];

    public static function canEdit(Model $record): bool
    {
        return ! $record->isPosted();
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isPosted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                DatePicker::make('date')->label(__('التاريخ'))->default(now())->required(),
                Select::make('from_warehouse_id')->label(__('من مخزن'))->relationship('fromWarehouse', 'name')->required()->different('to_warehouse_id'),
                Select::make('to_warehouse_id')->label(__('إلى مخزن'))->relationship('toWarehouse', 'name')->required(),
                TextInput::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
            ]),
            Section::make(__('الأصناف'))->columnSpanFull()->schema([
                Repeater::make('items')->hiddenLabel()->relationship()
                    ->table([TableColumn::make(__('الصنف'))->markAsRequired()->width('50%'), TableColumn::make(__('الكمية'))->markAsRequired()])
                    ->schema([
                        Select::make('product_id')->hiddenLabel()->required()->searchable()
                            ->options(fn () => Product::query()->where('track_stock', true)->pluck('name', 'id')),
                        TextInput::make('quantity')->hiddenLabel()->numeric()->required()->minValue(0.001),
                    ])
                    ->minItems(1)->addActionLabel(__('إضافة صنف')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label(__('الرقم')),
                TextEntry::make('date')->label(__('التاريخ'))->date('Y-m-d'),
                TextEntry::make('fromWarehouse.name')->label(__('من')),
                TextEntry::make('toWarehouse.name')->label(__('إلى')),
                TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(Labels::STATUS[$state] ?? $state))->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-')->columnSpan(3),
            ]),
            RepeatableEntry::make('items')->label(__('الأصناف'))->columnSpanFull()
                ->table([EntryColumn::make(__('الصنف')), EntryColumn::make(__('الكمية'))])
                ->schema([TextEntry::make('product.name'), TextEntry::make('quantity')->numeric(3)]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('الرقم'))->searchable(),
                TextColumn::make('date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('fromWarehouse.name')->label(__('من')),
                TextColumn::make('toWarehouse.name')->label(__('إلى')),
                TextColumn::make('items_count')->label(__('عدد الأصناف'))->counts('items'),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(Labels::STATUS[$state] ?? $state))->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'view' => Pages\ViewStockTransfer::route('/{record}'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
