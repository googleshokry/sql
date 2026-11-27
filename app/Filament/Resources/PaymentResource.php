<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\Treasury;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** سندات القبض والصرف */
class PaymentResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'سند';

    protected static ?string $pluralModelLabel = 'سندات القبض والصرف';

    protected static array $roles = ['accountant', 'manager', 'sales'];

    public const PARTY_TYPES = [
        Customer::class => 'عميل',
        Supplier::class => 'مورد',
        Employee::class => 'موظف',
        '' => 'أخرى (حساب مباشر)',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                ToggleButtons::make('direction')->label('نوع السند')->inline()->required()->default('in')
                    ->options(['in' => 'سند قبض', 'out' => 'سند صرف'])
                    ->colors(['in' => 'success', 'out' => 'danger'])
                    ->icons(['in' => Heroicon::OutlinedArrowDownTray, 'out' => Heroicon::OutlinedArrowUpTray])
                    ->live(),
                DatePicker::make('date')->label('التاريخ')->default(now())->required(),
                Select::make('treasury_id')->label('الخزينة / البنك')->required()
                    ->options(fn () => Treasury::query()->where('is_active', true)->pluck('name', 'id'))
                    ->default(fn () => Treasury::query()->where('is_default', true)->value('id')),

                Select::make('party_type')->label('الطرف')->options(self::PARTY_TYPES)->default(Customer::class)->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('party_id', null);
                        $set('payable_id', null);
                    }),
                Select::make('party_id')->label('الاسم')->searchable()->live()
                    ->visible(fn (Get $get) => filled($get('party_type')))
                    ->required(fn (Get $get) => filled($get('party_type')))
                    ->options(fn (Get $get) => filled($get('party_type')) && class_exists($get('party_type'))
                        ? $get('party_type')::query()->pluck('name', 'id') : [])
                    ->afterStateUpdated(fn (Set $set) => $set('payable_id', null)),
                Select::make('account_id')->label('الحساب المقابل')->searchable()
                    ->relationship('account', 'name', fn (Builder $query) => $query->where('is_group', false))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                    ->visible(fn (Get $get) => blank($get('party_type')))
                    ->required(fn (Get $get) => blank($get('party_type'))),
                Select::make('payable_id')->label('مقابل فاتورة (اختياري)')->searchable()
                    ->visible(fn (Get $get) => in_array($get('party_type'), [Customer::class, Supplier::class]))
                    ->options(function (Get $get) {
                        $model = $get('party_type') === Customer::class ? SalesInvoice::class : PurchaseInvoice::class;
                        $key = $get('party_type') === Customer::class ? 'customer_id' : 'supplier_id';

                        return $model::query()->where($key, $get('party_id'))->whereNotNull('posted_at')
                            ->where(fn ($q) => $q->where('payment_status', '!=', 'paid')->orWhere('id', $get('payable_id')))
                            ->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->number} - المتبقي ".number_format($i->remainingAmount(), 2)]);
                    })
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $model = $get('party_type') === Customer::class ? SalesInvoice::class : PurchaseInvoice::class;
                        if ($state && ($invoice = $model::find($state))) {
                            $set('amount', $invoice->remainingAmount());
                        }
                    })
                    ->live(),

                TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0.01),
                Select::make('method')->label('طريقة الدفع')->options(Payment::METHODS)->default('cash')->required(),
                TextInput::make('reference')->label('رقم مرجعي / شيك'),
                TextInput::make('notes')->label('البيان')->columnSpanFull(),
            ]),
        ]);
    }

    /** يحوّل payable_id إلى نوع الفاتورة المناسب */
    public static function normalizeData(array $data): array
    {
        $data['party_type'] = filled($data['party_type'] ?? null) ? $data['party_type'] : null;
        if (! $data['party_type']) {
            $data['party_id'] = null;
        } else {
            $data['account_id'] = null;
        }
        $data['payable_type'] = null;
        if (! empty($data['payable_id'])) {
            $data['payable_type'] = $data['party_type'] === Customer::class ? SalesInvoice::class : PurchaseInvoice::class;
        } else {
            $data['payable_id'] = null;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['party', 'treasury', 'payable', 'account']))
            ->columns([
                TextColumn::make('number')->label('الرقم')->searchable()->weight('bold'),
                TextColumn::make('direction')->label('النوع')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'in' ? 'قبض' : 'صرف')
                    ->color(fn ($state) => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('party_name')->label('الطرف')->state(fn (Payment $record) => $record->party?->name ?? $record->account?->name ?? '-'),
                TextColumn::make('payable_number')->label('الفاتورة')->state(fn (Payment $record) => $record->payable?->number ?? '-'),
                TextColumn::make('treasury.name')->label('الخزينة'),
                TextColumn::make('method')->label('الطريقة')->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state)->toggleable(),
                TextColumn::make('amount')->label('المبلغ')->numeric(2)->summarize(Sum::make()->label('')),
                TextColumn::make('notes')->label('البيان')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('direction')->label('النوع')->options(['in' => 'قبض', 'out' => 'صرف']),
                SelectFilter::make('treasury_id')->label('الخزينة')->relationship('treasury', 'name'),
                SelectFilter::make('method')->label('الطريقة')->options(Payment::METHODS),
                Filter::make('date')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('to')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('print')->label('طباعة')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->url(fn (Payment $record) => route('print.document', ['payment', $record]))->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
