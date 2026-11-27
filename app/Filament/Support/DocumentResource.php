<?php

namespace App\Filament\Support;

use App\Filament\Actions\DocumentActions;
use App\Filament\Concerns\HasRoleAccess;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\DocumentTotals;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as EntryColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * أساس مشترك لكل شاشات المستندات ذات البنود.
 */
abstract class DocumentResource extends Resource
{
    use HasRoleAccess;

    /** customer | supplier */
    protected static string $partyType = 'customer';

    /** sale | purchase */
    protected static string $priceSource = 'sale';

    protected static bool $payable = false;

    /** هل المستند يؤثر على المخزون/الحسابات (يُرحّل)؟ */
    protected static bool $postable = true;

    protected static ?string $printKey = null;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getPrintKey(): ?string
    {
        return static::$printKey;
    }

    public static function isPostable(): bool
    {
        return static::$postable;
    }

    public static function isPayable(): bool
    {
        return static::$payable;
    }

    public static function partyType(): string
    {
        return static::$partyType;
    }

    public static function canEdit(Model $record): bool
    {
        return ! $record->isPosted() && ! in_array($record->status, ['invoiced', 'received']);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isPosted();
    }

    /** حقول إضافية في رأس المستند */
    protected static function extraHeaderFields(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
    {
        $party = static::$partyType;
        $partyLabel = $party === 'customer' ? 'العميل' : 'المورد';

        return $schema->components([
            Section::make('بيانات المستند')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Select::make("{$party}_id")
                        ->label($partyLabel)
                        ->relationship($party, 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable(['name', 'phone', 'code'])
                        ->preload()
                        ->live()
                        ->required($party === 'supplier')
                        ->createOptionForm(static::quickPartyForm())
                        ->columnSpan(2),
                    DatePicker::make('date')->label('التاريخ')->default(now())->required(),
                    Select::make('warehouse_id')
                        ->label('المخزن')
                        ->relationship('warehouse', 'name')
                        ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'))
                        ->required(static::$postable),
                    ...static::extraHeaderFields(),
                ]),

            Section::make('البنود')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->relationship()
                        ->table([
                            TableColumn::make('الصنف')->markAsRequired()->width('34%'),
                            TableColumn::make('الكمية')->width('11%'),
                            TableColumn::make('السعر')->width('14%'),
                            TableColumn::make('خصم %')->width('10%'),
                            TableColumn::make('ضريبة %')->width('10%'),
                            TableColumn::make('الإجمالي')->width('14%'),
                        ])
                        ->schema(static::itemSchema())
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('إضافة بند')
                        ->live()
                        ->reorderable(false),
                ]),

            Grid::make(3)->columnSpanFull()->schema([
                Section::make('ملاحظات')->columnSpan(2)->schema([
                    Textarea::make('notes')->label('ملاحظات')->rows(2),
                    Textarea::make('terms')->label('الشروط والأحكام')->rows(2)
                        ->default(fn () => static::$partyType === 'customer' ? Setting::get('invoice_terms') : null),
                ]),
                Section::make('الإجمالي')->columnSpan(1)->schema([
                    Grid::make(2)->schema([
                        Select::make('discount_type')->label('نوع الخصم')
                            ->options(['fixed' => 'مبلغ', 'percent' => 'نسبة %'])->default('fixed')->live()->selectablePlaceholder(false),
                        TextInput::make('discount_value')->label('الخصم')->numeric()->default(0)->live(onBlur: true),
                    ]),
                    TextInput::make('shipping')->label('الشحن / مصاريف إضافية')->numeric()->default(0)->live(onBlur: true),
                    TextEntry::make('totals_preview')
                        ->hiddenLabel()
                        ->state(fn (Get $get) => static::totalsHtml($get)),
                ]),
            ]),
        ]);
    }

    protected static function itemSchema(): array
    {
        $priceSource = static::$priceSource;
        $party = static::$partyType;

        return [
            Select::make('product_id')
                ->hiddenLabel()
                ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->get()
                    ->mapWithKeys(fn (Product $p) => [$p->id => $p->name.($p->barcode ? " ({$p->barcode})" : '')]))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($priceSource, $party) {
                    $product = Product::find($state);
                    if (! $product) {
                        return;
                    }
                    $priceList = $party === 'customer' ? Customer::find($get('../../customer_id'))?->price_list : null;
                    $set('unit_price', $priceSource === 'sale' ? $product->priceFor($priceList) : (float) $product->purchase_price);
                    $set('tax_rate', (float) $product->tax_rate);
                    $set('quantity', $get('quantity') ?: 1);
                }),
            TextInput::make('quantity')->hiddenLabel()->numeric()->default(1)->minValue(0.001)->required()->live(onBlur: true),
            TextInput::make('unit_price')->hiddenLabel()->numeric()->default(0)->required()->live(onBlur: true),
            TextInput::make('discount_percent')->hiddenLabel()->numeric()->default(0)->minValue(0)->maxValue(100)->live(onBlur: true),
            TextInput::make('tax_rate')->hiddenLabel()->numeric()->default(fn () => (float) Setting::get('default_tax_rate', 15))->live(onBlur: true),
            TextEntry::make('line_total')->hiddenLabel()
                ->state(function (Get $get) {
                    $line = DocumentTotals::line(['quantity' => $get('quantity'), 'unit_price' => $get('unit_price'), 'discount_percent' => $get('discount_percent'), 'tax_rate' => $get('tax_rate')]);

                    return number_format($line['total'] + $line['tax_amount'], 2);
                }),
        ];
    }

    protected static function totalsHtml(Get $get): HtmlString
    {
        $t = DocumentTotals::calculate(
            array_values($get('items') ?? []),
            $get('discount_type'),
            (float) $get('discount_value'),
            (float) $get('shipping'),
        );
        $c = Money::currency();
        $row = fn ($label, $value, $bold = false) => '<div style="display:flex;justify-content:space-between;padding:4px 0;'.($bold ? 'font-weight:700;font-size:1.1em;border-top:1px solid #ddd;margin-top:4px;padding-top:8px' : '').'"><span>'.$label.'</span><span>'.number_format($value, 2).' '.$c.'</span></div>';

        return new HtmlString(
            $row('الإجمالي قبل الخصم', $t['subtotal'])
            .$row('الخصم', $t['discount_amount'])
            .$row('الضريبة', $t['tax_amount'])
            .$row('الشحن', $t['shipping'])
            .$row('الإجمالي النهائي', $t['total'], true)
        );
    }

    public static function quickPartyForm(): array
    {
        return [
            TextInput::make('name')->label('الاسم')->required(),
            TextInput::make('phone')->label('الهاتف'),
            TextInput::make('tax_number')->label('الرقم الضريبي'),
        ];
    }

    public static function table(Table $table): Table
    {
        $party = static::$partyType;

        $columns = [
            TextColumn::make('number')->label('الرقم')->searchable()->sortable()->weight('bold'),
            TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
            TextColumn::make("{$party}.name")->label($party === 'customer' ? 'العميل' : 'المورد')->searchable()->placeholder('نقدي'),
            TextColumn::make('total')->label('الإجمالي')->numeric(2)->sortable()->summarize(Sum::make()->label('')),
        ];

        if (static::$payable) {
            $columns[] = TextColumn::make('paid_amount')->label('المدفوع')->numeric(2)->toggleable();
            $columns[] = TextColumn::make('payment_status')->label('حالة السداد')->badge()
                ->formatStateUsing(fn ($state) => Labels::PAYMENT_STATUS[$state] ?? $state)
                ->color(fn ($state) => Labels::PAYMENT_STATUS_COLORS[$state] ?? 'gray');
        }

        $columns[] = TextColumn::make('status')->label('الحالة')->badge()
            ->formatStateUsing(fn ($state) => Labels::STATUS[$state] ?? $state)
            ->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray');
        $columns[] = TextColumn::make('warehouse.name')->label('المخزن')->toggleable(isToggledHiddenByDefault: true);
        $columns[] = TextColumn::make('creator.name')->label('بواسطة')->toggleable(isToggledHiddenByDefault: true);

        $filters = [
            SelectFilter::make('status')->label('الحالة')->options(Labels::STATUS),
            SelectFilter::make("{$party}_id")->label($party === 'customer' ? 'العميل' : 'المورد')->relationship($party, 'name')->searchable()->preload(),
            Filter::make('date')
                ->schema([
                    DatePicker::make('from')->label('من تاريخ'),
                    DatePicker::make('to')->label('إلى تاريخ'),
                ])
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                    ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))),
        ];
        if (static::$payable) {
            array_splice($filters, 1, 0, [SelectFilter::make('payment_status')->label('حالة السداد')->options(Labels::PAYMENT_STATUS)]);
        }

        return $table
            ->columns($columns)
            ->filters($filters)
            ->defaultSort('id', 'desc')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...DocumentActions::forTable(static::class),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $party = static::$partyType;

        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label('الرقم')->weight('bold'),
                TextEntry::make('date')->label('التاريخ')->date('Y-m-d'),
                TextEntry::make("{$party}.name")->label($party === 'customer' ? 'العميل' : 'المورد')->placeholder('نقدي'),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => Labels::STATUS[$state] ?? $state)
                    ->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('warehouse.name')->label('المخزن'),
                TextEntry::make('payment_status')->label('حالة السداد')->badge()->visible(static::$payable)
                    ->formatStateUsing(fn ($state) => Labels::PAYMENT_STATUS[$state] ?? $state)
                    ->color(fn ($state) => Labels::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('creator.name')->label('بواسطة'),
                TextEntry::make('posted_at')->label('تاريخ الترحيل')->dateTime('Y-m-d H:i')->placeholder('-')->visible(static::$postable),
            ]),
            Section::make('البنود')->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->table([
                    EntryColumn::make('الصنف'),
                    EntryColumn::make('الكمية'),
                    EntryColumn::make('السعر'),
                    EntryColumn::make('خصم %'),
                    EntryColumn::make('الضريبة'),
                    EntryColumn::make('الإجمالي'),
                ])->schema([
                    TextEntry::make('description'),
                    TextEntry::make('quantity')->numeric(3),
                    TextEntry::make('unit_price')->numeric(2),
                    TextEntry::make('discount_percent')->numeric(2),
                    TextEntry::make('tax_amount')->numeric(2),
                    TextEntry::make('line_total')->state(fn ($record) => number_format((float) $record->total + (float) $record->tax_amount, 2)),
                ]),
            ]),
            Grid::make(3)->columnSpanFull()->schema([
                Section::make('ملاحظات')->columnSpan(2)->schema([
                    TextEntry::make('notes')->label('ملاحظات')->placeholder('-'),
                    TextEntry::make('terms')->label('الشروط')->placeholder('-'),
                ]),
                Section::make('الإجمالي')->columnSpan(1)->columns(2)->schema([
                    TextEntry::make('subtotal')->label('قبل الخصم')->numeric(2),
                    TextEntry::make('discount_amount')->label('الخصم')->numeric(2),
                    TextEntry::make('tax_amount')->label('الضريبة')->numeric(2),
                    TextEntry::make('shipping')->label('الشحن')->numeric(2),
                    TextEntry::make('total')->label('الإجمالي')->numeric(2)->weight('bold')->size('lg'),
                    TextEntry::make('paid_amount')->label('المدفوع')->numeric(2)->visible(static::$payable),
                    TextEntry::make('remaining')->label('المتبقي')->visible(static::$payable)->color('danger')->weight('bold')
                        ->state(fn ($record) => number_format($record->remainingAmount(), 2)),
                ]),
            ]),
        ]);
    }
}
