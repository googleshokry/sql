<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Filament\Support\Labels;
use App\Models\Product;
use App\Models\StockAdjustment;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** الجرد وتسوية المخزون */
class StockAdjustmentResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = StockAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'جرد / تسوية';

    protected static ?string $pluralModelLabel = 'الجرد والتسويات';

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
                DatePicker::make('date')->label('تاريخ الجرد')->default(now())->required(),
                Select::make('warehouse_id')->label('المخزن')->relationship('warehouse', 'name')->required()->live(),
                TextInput::make('notes')->label('ملاحظات'),
            ]),
            Section::make('الأصناف')->description('أدخل الكمية الفعلية الموجودة بالمخزن وسيتم حساب الفرق وتسويته تلقائياً عند الترحيل')->columnSpanFull()->schema([
                Repeater::make('items')->hiddenLabel()->relationship()
                    ->table([TableColumn::make('الصنف')->markAsRequired()->width('50%'), TableColumn::make('الكمية بالنظام'), TableColumn::make('الكمية الفعلية')->markAsRequired()])
                    ->schema([
                        Select::make('product_id')->hiddenLabel()->required()->searchable()->live()
                            ->options(fn () => Product::query()->where('track_stock', true)->pluck('name', 'id'))
                            ->afterStateUpdated(fn ($state, Get $get, Set $set) => $set('system_quantity', $state ? Product::find($state)?->quantity($get('../../warehouse_id')) : 0)),
                        TextInput::make('system_quantity')->hiddenLabel()->numeric()->disabled()->dehydrated(),
                        TextInput::make('actual_quantity')->hiddenLabel()->numeric()->required(),
                    ])
                    ->minItems(1)->addActionLabel('إضافة صنف'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label('الرقم'),
                TextEntry::make('date')->label('التاريخ')->date('Y-m-d'),
                TextEntry::make('warehouse.name')->label('المخزن'),
                TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => Labels::STATUS[$state] ?? $state)->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
            ]),
            RepeatableEntry::make('items')->label('الأصناف')->columnSpanFull()
                ->table([EntryColumn::make('الصنف'), EntryColumn::make('بالنظام'), EntryColumn::make('الفعلي'), EntryColumn::make('الفرق')])
                ->schema([
                    TextEntry::make('product.name'),
                    TextEntry::make('system_quantity')->numeric(3),
                    TextEntry::make('actual_quantity')->numeric(3),
                    TextEntry::make('difference')->numeric(3)->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('الرقم')->searchable(),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('warehouse.name')->label('المخزن'),
                TextColumn::make('items_count')->label('عدد الأصناف')->counts('items'),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => Labels::STATUS[$state] ?? $state)->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
