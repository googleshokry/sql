<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\Inventory;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ProductResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'صنف';

    protected static ?string $pluralModelLabel = 'الأصناف والخدمات';

    protected static ?string $recordTitleAttribute = 'name';

    protected static array $roles = ['storekeeper', 'manager', 'purchases', 'sales'];

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية')->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('اسم الصنف')->required()->columnSpan(2),
                Select::make('type')->label('النوع')->options(['product' => 'منتج مخزني', 'service' => 'خدمة'])->default('product')->live()->required(),
                TextInput::make('sku')->label('الكود (SKU)')->placeholder('تلقائي')->unique(ignoreRecord: true),
                TextInput::make('barcode')->label('الباركود')->unique(ignoreRecord: true),
                Select::make('category_id')->label('التصنيف')->relationship('category', 'name')->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->label('الاسم')->required()]),
                Select::make('unit_id')->label('الوحدة')->relationship('unit', 'name')->preload()
                    ->createOptionForm([TextInput::make('name')->label('الاسم')->required()]),
                FileUpload::make('image')->label('الصورة')->image()->directory('products')->columnSpan(2),
            ]),
            Section::make('الأسعار والضريبة')->columns(4)->columnSpanFull()->schema([
                TextInput::make('purchase_price')->label('سعر الشراء')->numeric()->default(0),
                TextInput::make('sale_price')->label('سعر البيع (قطاعي)')->numeric()->default(0)->required(),
                TextInput::make('wholesale_price')->label('سعر الجملة')->numeric()->default(0),
                TextInput::make('min_sale_price')->label('أقل سعر بيع')->numeric()->default(0),
                TextInput::make('tax_rate')->label('نسبة الضريبة %')->numeric()->default(fn () => (float) Setting::get('default_tax_rate', 15)),
                Toggle::make('price_includes_tax')->label('السعر شامل الضريبة')->inline(false),
                TextInput::make('cost_price')->label('متوسط التكلفة')->numeric()->disabled()->visibleOn('edit'),
            ]),
            Section::make('المخزون')->columns(4)->columnSpanFull()->visible(fn (Get $get) => $get('type') !== 'service')->schema([
                Toggle::make('track_stock')->label('تتبع المخزون')->default(true)->inline(false),
                TextInput::make('min_stock')->label('حد الطلب (تنبيه)')->numeric()->default(0),
                TextInput::make('opening_quantity')->label('رصيد أول المدة')->numeric()->default(0)->visibleOn('create')->dehydrated(false),
                Select::make('opening_warehouse_id')->label('في مخزن')->options(fn () => Warehouse::query()->pluck('name', 'id'))
                    ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'))->visibleOn('create')->dehydrated(false),
            ]),
            Section::make()->columnSpanFull()->schema([
                Textarea::make('description')->label('الوصف'),
                Toggle::make('is_active')->label('نشط')->default(true),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('name')->label('الاسم')->weight('bold')->columnSpan(2),
                TextEntry::make('sku')->label('الكود'),
                TextEntry::make('barcode')->label('الباركود')->placeholder('-'),
                TextEntry::make('category.name')->label('التصنيف')->placeholder('-'),
                TextEntry::make('unit.name')->label('الوحدة')->placeholder('-'),
                TextEntry::make('sale_price')->label('سعر البيع')->numeric(2),
                TextEntry::make('wholesale_price')->label('سعر الجملة')->numeric(2),
                TextEntry::make('purchase_price')->label('آخر سعر شراء')->numeric(2),
                TextEntry::make('cost_price')->label('متوسط التكلفة')->numeric(2),
                TextEntry::make('total_qty')->label('الكمية الإجمالية')->state(fn (Product $record) => $record->quantity())->numeric(3)->weight('bold')
                    ->color(fn (Product $record, $state) => $state <= (float) $record->min_stock ? 'danger' : 'success'),
                TextEntry::make('stock_value')->label('قيمة المخزون')->state(fn (Product $record) => $record->quantity() * (float) $record->cost_price)->numeric(2),
                ImageEntry::make('image')->label('الصورة')->visible(fn ($record) => $record->image),
            ]),
            Section::make('الكميات حسب المخزن')->columnSpanFull()->visible(fn ($record) => $record->track_stock)->schema([
                RepeatableEntry::make('stocks')->hiddenLabel()->table([TableColumn::make('المخزن'), TableColumn::make('الكمية')])->schema([
                    TextEntry::make('warehouse.name'),
                    TextEntry::make('quantity')->numeric(3),
                ]),
            ]),
            Section::make('آخر الحركات')->columnSpanFull()->visible(fn ($record) => $record->track_stock)->schema([
                RepeatableEntry::make('recent_movements')->hiddenLabel()
                    ->state(fn (Product $record) => $record->movements()->with('warehouse')->latest('id')->limit(15)->get())
                    ->table([TableColumn::make('التاريخ'), TableColumn::make('النوع'), TableColumn::make('المخزن'), TableColumn::make('الكمية'), TableColumn::make('التكلفة'), TableColumn::make('الرصيد بعد')])
                    ->schema([
                        TextEntry::make('date')->date('Y-m-d'),
                        TextEntry::make('type')->formatStateUsing(fn ($state) => Inventory::TYPES[$state] ?? $state),
                        TextEntry::make('warehouse.name'),
                        TextEntry::make('quantity')->numeric(3)->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        TextEntry::make('unit_cost')->numeric(2),
                        TextEntry::make('balance_after')->numeric(3),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('stocks as stock_qty', 'quantity')->with('category'))
            ->columns([
                ImageColumn::make('image')->label('')->circular()->toggleable(),
                TextColumn::make('sku')->label('الكود')->searchable()->sortable(),
                TextColumn::make('barcode')->label('الباركود')->searchable()->toggleable(),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable()->weight('bold'),
                TextColumn::make('category.name')->label('التصنيف')->toggleable(),
                TextColumn::make('sale_price')->label('سعر البيع')->numeric(2)->sortable(),
                TextColumn::make('cost_price')->label('التكلفة')->numeric(2)->toggleable(),
                TextColumn::make('stock_qty')->label('الكمية')->numeric(3)->sortable()->placeholder('خدمة')
                    ->color(fn (Product $record, $state) => $record->track_stock && $state <= (float) $record->min_stock ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('التصنيف')->relationship('category', 'name'),
                SelectFilter::make('type')->label('النوع')->options(['product' => 'منتج', 'service' => 'خدمة']),
                Filter::make('low_stock')->label('أصناف وصلت لحد الطلب')->query(fn (Builder $query) => $query->lowStock()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('barcodes')->label('طباعة باركود')->icon(Heroicon::OutlinedQrCode)
                        ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('print.barcodes', ['ids' => $records->pluck('id')->implode(',')])).')')),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
