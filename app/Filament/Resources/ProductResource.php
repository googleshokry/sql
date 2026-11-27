<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\Inventory;
use App\Support\Tenancy;
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
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ProductResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

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
            Section::make(__('البيانات الأساسية'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('اسم الصنف'))->required()->columnSpan(2),
                Select::make('type')->label(__('النوع'))->options(['product' => __('منتج مخزني'), 'service' => __('خدمة')])->default('product')->live()->required(),
                TextInput::make('sku')->label(__('الكود (SKU)'))->placeholder(__('تلقائي'))->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('company_id', Tenancy::id())),
                TextInput::make('barcode')->label(__('الباركود'))->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('company_id', Tenancy::id())),
                Select::make('category_id')->label(__('التصنيف'))->relationship('category', 'name')->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('الاسم'))->required()]),
                Select::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'name')->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('الاسم'))->required()]),
                FileUpload::make('image')->label(__('الصورة'))->image()->directory('products')->columnSpan(2),
            ]),
            Section::make(__('الأسعار والضريبة'))->columns(4)->columnSpanFull()->schema([
                TextInput::make('purchase_price')->label(__('سعر الشراء'))->numeric()->default(0),
                TextInput::make('sale_price')->label(__('سعر البيع (قطاعي)'))->numeric()->default(0)->required(),
                TextInput::make('wholesale_price')->label(__('سعر الجملة'))->numeric()->default(0),
                TextInput::make('min_sale_price')->label(__('أقل سعر بيع'))->numeric()->default(0),
                TextInput::make('tax_rate')->label(__('نسبة الضريبة %'))->numeric()->default(fn () => (float) Setting::get('default_tax_rate', 15)),
                Toggle::make('price_includes_tax')->label(__('السعر شامل الضريبة'))->inline(false),
                TextInput::make('cost_price')->label(__('متوسط التكلفة'))->numeric()->disabled()->visibleOn('edit'),
            ]),
            Section::make(__('المخزون'))->columns(4)->columnSpanFull()->visible(fn (Get $get) => $get('type') !== 'service')->schema([
                Toggle::make('track_stock')->label(__('تتبع المخزون'))->default(true)->inline(false),
                TextInput::make('min_stock')->label(__('حد الطلب (تنبيه)'))->numeric()->default(0),
                TextInput::make('opening_quantity')->label(__('رصيد أول المدة'))->numeric()->default(0)->visibleOn('create')->dehydrated(false),
                Select::make('opening_warehouse_id')->label(__('في مخزن'))->options(fn () => Warehouse::query()->pluck('name', 'id'))
                    ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'))->visibleOn('create')->dehydrated(false),
            ]),
            Section::make()->columnSpanFull()->schema([
                Textarea::make('description')->label(__('الوصف')),
                Toggle::make('is_active')->label(__('نشط'))->default(true),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('name')->label(__('الاسم'))->weight('bold')->columnSpan(2),
                TextEntry::make('sku')->label(__('الكود')),
                TextEntry::make('barcode')->label(__('الباركود'))->placeholder('-'),
                TextEntry::make('category.name')->label(__('التصنيف'))->placeholder('-'),
                TextEntry::make('unit.name')->label(__('الوحدة'))->placeholder('-'),
                TextEntry::make('sale_price')->label(__('سعر البيع'))->numeric(2),
                TextEntry::make('wholesale_price')->label(__('سعر الجملة'))->numeric(2),
                TextEntry::make('purchase_price')->label(__('آخر سعر شراء'))->numeric(2),
                TextEntry::make('cost_price')->label(__('متوسط التكلفة'))->numeric(2),
                TextEntry::make('total_qty')->label(__('الكمية الإجمالية'))->state(fn (Product $record) => $record->quantity())->numeric(3)->weight('bold')
                    ->color(fn (Product $record, $state) => $state <= (float) $record->min_stock ? 'danger' : 'success'),
                TextEntry::make('stock_value')->label(__('قيمة المخزون'))->state(fn (Product $record) => $record->quantity() * (float) $record->cost_price)->numeric(2),
                ImageEntry::make('image')->label(__('الصورة'))->visible(fn ($record) => $record->image),
            ]),
            Section::make(__('الكميات حسب المخزن'))->columnSpanFull()->visible(fn ($record) => $record->track_stock)->schema([
                RepeatableEntry::make('stocks')->hiddenLabel()->table([TableColumn::make(__('المخزن')), TableColumn::make(__('الكمية'))])->schema([
                    TextEntry::make('warehouse.name'),
                    TextEntry::make('quantity')->numeric(3),
                ]),
            ]),
            Section::make(__('آخر الحركات'))->columnSpanFull()->visible(fn ($record) => $record->track_stock)->schema([
                RepeatableEntry::make('recent_movements')->hiddenLabel()
                    ->state(fn (Product $record) => $record->movements()->with('warehouse')->latest('id')->limit(15)->get())
                    ->table([TableColumn::make(__('التاريخ')), TableColumn::make(__('النوع')), TableColumn::make(__('المخزن')), TableColumn::make(__('الكمية')), TableColumn::make(__('التكلفة')), TableColumn::make(__('الرصيد بعد'))])
                    ->schema([
                        TextEntry::make('date')->date('Y-m-d'),
                        TextEntry::make('type')->formatStateUsing(fn ($state) => __(Inventory::TYPES[$state] ?? $state)),
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
                TextColumn::make('sku')->label(__('الكود'))->searchable()->sortable(),
                TextColumn::make('barcode')->label(__('الباركود'))->searchable()->toggleable(),
                TextColumn::make('name')->label(__('الاسم'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('category.name')->label(__('التصنيف'))->toggleable(),
                TextColumn::make('sale_price')->label(__('سعر البيع'))->numeric(2)->sortable(),
                TextColumn::make('cost_price')->label(__('التكلفة'))->numeric(2)->toggleable(),
                TextColumn::make('stock_qty')->label(__('الكمية'))->numeric(3)->sortable()->placeholder(__('خدمة'))
                    ->color(fn (Product $record, $state) => $record->track_stock && $state <= (float) $record->min_stock ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('category_id')->label(__('التصنيف'))->relationship('category', 'name'),
                SelectFilter::make('type')->label(__('النوع'))->options(['product' => __('منتج'), 'service' => __('خدمة')]),
                Filter::make('low_stock')->label(__('أصناف وصلت لحد الطلب'))->query(fn (Builder $query) => $query->lowStock()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('barcodes')->label(__('طباعة باركود'))->icon(Heroicon::OutlinedQrCode)
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
