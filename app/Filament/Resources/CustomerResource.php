<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class CustomerResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static ?string $recordTitleAttribute = 'name';

    protected static array $roles = ['sales', 'manager', 'accountant'];

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'mobile', 'code', 'tax_number'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('البيانات الأساسية'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('code')->label(__('الكود'))->placeholder(__('تلقائي'))->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('company_id', Tenancy::id())),
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('company_name')->label(__('اسم الشركة / المنشأة')),
                TextInput::make('phone')->label(__('الهاتف'))->tel(),
                TextInput::make('mobile')->label(__('الجوال / واتساب'))->tel(),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email(),
            ]),
            Section::make(__('البيانات الضريبية والعنوان'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('tax_number')->label(__('الرقم الضريبي (VAT)')),
                TextInput::make('commercial_register')->label(__('السجل التجاري')),
                TextInput::make('city')->label(__('المدينة')),
                TextInput::make('address')->label(__('العنوان'))->columnSpanFull(),
            ]),
            Section::make(__('البيانات المالية'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('opening_balance')->label(__('الرصيد الافتتاحي (مستحق لنا)'))->numeric()->default(0),
                TextInput::make('credit_limit')->label(__('حد الائتمان'))->numeric()->default(0),
                Select::make('price_list')->label(__('قائمة الأسعار'))->options(['retail' => __('قطاعي'), 'wholesale' => __('جملة')])->default('retail'),
                Toggle::make('is_active')->label(__('نشط'))->default(true),
                Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('code')->label(__('الكود')),
                TextEntry::make('name')->label(__('الاسم'))->weight('bold'),
                TextEntry::make('phone')->label(__('الهاتف'))->placeholder('-'),
                TextEntry::make('mobile')->label(__('الجوال'))->placeholder('-'),
                TextEntry::make('email')->label(__('البريد'))->placeholder('-'),
                TextEntry::make('tax_number')->label(__('الرقم الضريبي'))->placeholder('-'),
                TextEntry::make('city')->label(__('المدينة'))->placeholder('-'),
                TextEntry::make('address')->label(__('العنوان'))->placeholder('-'),
            ]),
            Section::make(__('الملخص المالي'))->columns(4)->columnSpanFull()->schema([
                TextEntry::make('balance')->label(__('الرصيد الحالي (مستحق لنا)'))->state(fn ($record) => $record->balance())->numeric(2)->weight('bold')->size('lg')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextEntry::make('invoices_total')->label(__('إجمالي الفواتير'))->state(fn ($record) => $record->invoices()->whereNotNull('posted_at')->sum('total'))->numeric(2),
                TextEntry::make('invoices_count')->label(__('عدد الفواتير'))->state(fn ($record) => $record->invoices()->count()),
                TextEntry::make('payments_total')->label(__('إجمالي المدفوعات'))->state(fn ($record) => $record->payments()->sum('amount'))->numeric(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('الكود'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('الاسم'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('phone')->label(__('الهاتف'))->searchable(),
                TextColumn::make('city')->label(__('المدينة'))->toggleable(),
                TextColumn::make('tax_number')->label(__('الرقم الضريبي'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')->label(__('الرصيد'))->state(fn ($record) => $record->balance())->numeric(2)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                IconColumn::make('is_active')->label(__('نشط'))->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('نشط'))])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
