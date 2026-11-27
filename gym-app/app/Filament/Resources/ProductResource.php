<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'المتجر والبوفيه';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات والمخزون';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $low = Product::where('is_active', true)->whereColumn('stock', '<=', 'low_stock_alert')->count();

        return $low ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('اسم المنتج')->required()->columnSpan(2),
                TextInput::make('sku')->label('الكود / الباركود')->unique(ignoreRecord: true),
                TextInput::make('category')->label('التصنيف')->placeholder('مكملات، مشروبات، ملابس ...')
                    ->datalist(fn () => Product::query()->distinct()->pluck('category')->filter()->all()),
                TextInput::make('cost_price')->label('سعر التكلفة')->numeric()->default(0)->suffix(Setting::currency()),
                TextInput::make('sale_price')->label('سعر البيع')->numeric()->required()->suffix(Setting::currency()),
                TextInput::make('stock')->label('الكمية بالمخزن')->numeric()->default(0)->required(),
                TextInput::make('low_stock_alert')->label('تنبيه عند وصول الكمية إلى')->numeric()->default(5),
                Toggle::make('is_active')->label('متاح للبيع')->default(true)->inline(false),
                FileUpload::make('image')->label('الصورة')->image()->directory('products')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->square(),
                TextColumn::make('name')->label('المنتج')->searchable()->sortable(),
                TextColumn::make('sku')->label('الكود')->searchable()->toggleable(),
                TextColumn::make('category')->label('التصنيف')->badge()->searchable(),
                TextColumn::make('cost_price')->label('التكلفة')->numeric(2, locale: 'en')->toggleable(),
                TextColumn::make('sale_price')->label('سعر البيع')->numeric(2, locale: 'en')->sortable(),
                TextColumn::make('stock')->label('المخزون')->sortable()->badge()
                    ->color(fn (Product $r) => $r->stock <= $r->low_stock_alert ? 'danger' : 'success'),
                ToggleColumn::make('is_active')->label('متاح'),
            ])
            ->filters([
                Filter::make('low')->label('مخزون منخفض')
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<=', 'low_stock_alert')),
            ])
            ->recordActions([
                Action::make('restock')->label('إضافة مخزون')->icon(Heroicon::OutlinedPlusCircle)->color('success')
                    ->schema([TextInput::make('qty')->label('الكمية المضافة')->numeric()->minValue(1)->required()])
                    ->action(function (Product $record, array $data) {
                        $record->increment('stock', (int) $data['qty']);
                        Notification::make()->title('تم تحديث المخزون')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProducts::route('/')];
    }
}
