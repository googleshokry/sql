<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'المتجر والبوفيه';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'فاتورة بيع';

    protected static ?string $pluralModelLabel = 'المبيعات (نقطة بيع)';

    public static function form(Schema $schema): Schema
    {
        $cur = Setting::currency();

        return $schema->components([
            Section::make('الأصناف')->columnSpanFull()->schema([
                Repeater::make('items')->label('')->relationship()->minItems(1)->defaultItems(1)
                    ->addActionLabel('إضافة صنف')->columns(4)->live()
                    ->schema([
                        Select::make('product_id')->label('المنتج')->required()->searchable()->columnSpan(2)
                            ->options(fn () => Product::where('is_active', true)->get()
                                ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} — {$p->sale_price} {$cur} (متاح {$p->stock})"]))
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('unit_price', Product::find($state)?->sale_price))
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('quantity')->label('الكمية')->numeric()->minValue(1)->default(1)->required()->live(onBlur: true)
                            ->rule(fn (Get $get, ?\Illuminate\Database\Eloquent\Model $record) => function ($attr, $value, $fail) use ($get, $record) {
                                $p = Product::find($get('product_id'));
                                $available = ($p?->stock ?? 0) + (int) ($record?->quantity ?? 0);
                                if ($p && $value > $available) {
                                    $fail("الكمية المتاحة من {$p->name} هي {$available} فقط");
                                }
                            }),
                        TextInput::make('unit_price')->label('سعر الوحدة')->numeric()->required()->suffix($cur)->live(onBlur: true),
                    ]),
            ]),
            Grid::make(4)->columnSpanFull()->schema([
                Select::make('member_id')->label('المشترك (اختياري)')->relationship('member', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->name} ({$r->code})")
                    ->searchable(['name', 'code', 'phone']),
                Select::make('method')->label('طريقة الدفع')->options(Payment::METHODS)->default('cash')->required(),
                TextInput::make('discount')->label('الخصم')->numeric()->default(0)->suffix($cur)->live(onBlur: true),
                DateTimePicker::make('sold_at')->label('التاريخ')->default(now())->required(),
                TextEntry::make('grand_total')->label('الإجمالي المستحق')->columnSpanFull()->size('lg')->weight('bold')
                    ->state(function (Get $get) use ($cur) {
                        $sub = collect($get('items') ?? [])->sum(fn ($i) => (float) ($i['quantity'] ?? 0) * (float) ($i['unit_price'] ?? 0));

                        return number_format(max(0, $sub - (float) $get('discount')), 2).' '.$cur;
                    }),
                TextInput::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                TextEntry::make('invoice_number')->label('رقم الفاتورة'),
                TextEntry::make('sold_at')->label('التاريخ')->dateTime('Y-m-d h:i A'),
                TextEntry::make('member.name')->label('المشترك')->placeholder('عميل نقدي'),
                TextEntry::make('total')->label('الإجمالي')->numeric(2, locale: 'en'),
                \Filament\Infolists\Components\RepeatableEntry::make('items')->label('الأصناف')->columnSpanFull()->columns(4)->schema([
                    TextEntry::make('product.name')->label('المنتج'),
                    TextEntry::make('quantity')->label('الكمية'),
                    TextEntry::make('unit_price')->label('السعر'),
                    TextEntry::make('total')->label('الإجمالي'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('الفاتورة')->searchable(),
                TextColumn::make('sold_at')->label('التاريخ')->dateTime('Y-m-d h:i A')->sortable(),
                TextColumn::make('member.name')->label('المشترك')->placeholder('عميل نقدي')->searchable(),
                TextColumn::make('items_count')->label('الأصناف')->counts('items'),
                TextColumn::make('discount')->label('الخصم')->numeric(2, locale: 'en'),
                TextColumn::make('total')->label('الإجمالي')->numeric(2, locale: 'en')->summarize(Sum::make()->label('الإجمالي')->numeric(2, locale: 'en')),
                TextColumn::make('method')->label('الدفع')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                TextColumn::make('user.name')->label('البائع'),
            ])
            ->filters([
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('من'),
                    DatePicker::make('until')->label('إلى'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('print')->label('طباعة')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->url(fn (Sale $r) => route('print.sale', $r), true),
                DeleteAction::make()->visible(fn () => auth()->user()->isManager())
                    ->before(fn (Sale $r) => $r->items->each->delete()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
