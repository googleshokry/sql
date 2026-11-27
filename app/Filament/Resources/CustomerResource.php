<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
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
use UnitEnum;

class CustomerResource extends Resource
{
    use HasRoleAccess;

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
            Section::make('البيانات الأساسية')->columns(3)->columnSpanFull()->schema([
                TextInput::make('code')->label('الكود')->placeholder('تلقائي')->unique(ignoreRecord: true),
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('company_name')->label('اسم الشركة / المنشأة'),
                TextInput::make('phone')->label('الهاتف')->tel(),
                TextInput::make('mobile')->label('الجوال / واتساب')->tel(),
                TextInput::make('email')->label('البريد الإلكتروني')->email(),
            ]),
            Section::make('البيانات الضريبية والعنوان')->columns(3)->columnSpanFull()->schema([
                TextInput::make('tax_number')->label('الرقم الضريبي (VAT)'),
                TextInput::make('commercial_register')->label('السجل التجاري'),
                TextInput::make('city')->label('المدينة'),
                TextInput::make('address')->label('العنوان')->columnSpanFull(),
            ]),
            Section::make('البيانات المالية')->columns(3)->columnSpanFull()->schema([
                TextInput::make('opening_balance')->label('الرصيد الافتتاحي (مستحق لنا)')->numeric()->default(0),
                TextInput::make('credit_limit')->label('حد الائتمان')->numeric()->default(0),
                Select::make('price_list')->label('قائمة الأسعار')->options(['retail' => 'قطاعي', 'wholesale' => 'جملة'])->default('retail'),
                Toggle::make('is_active')->label('نشط')->default(true),
                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('code')->label('الكود'),
                TextEntry::make('name')->label('الاسم')->weight('bold'),
                TextEntry::make('phone')->label('الهاتف')->placeholder('-'),
                TextEntry::make('mobile')->label('الجوال')->placeholder('-'),
                TextEntry::make('email')->label('البريد')->placeholder('-'),
                TextEntry::make('tax_number')->label('الرقم الضريبي')->placeholder('-'),
                TextEntry::make('city')->label('المدينة')->placeholder('-'),
                TextEntry::make('address')->label('العنوان')->placeholder('-'),
            ]),
            Section::make('الملخص المالي')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('balance')->label('الرصيد الحالي (مستحق لنا)')->state(fn ($record) => $record->balance())->numeric(2)->weight('bold')->size('lg')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextEntry::make('invoices_total')->label('إجمالي الفواتير')->state(fn ($record) => $record->invoices()->whereNotNull('posted_at')->sum('total'))->numeric(2),
                TextEntry::make('invoices_count')->label('عدد الفواتير')->state(fn ($record) => $record->invoices()->count()),
                TextEntry::make('payments_total')->label('إجمالي المدفوعات')->state(fn ($record) => $record->payments()->sum('amount'))->numeric(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الكود')->searchable()->sortable(),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable()->weight('bold'),
                TextColumn::make('phone')->label('الهاتف')->searchable(),
                TextColumn::make('city')->label('المدينة')->toggleable(),
                TextColumn::make('tax_number')->label('الرقم الضريبي')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')->label('الرصيد')->state(fn ($record) => $record->balance())->numeric(2)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('نشط')])
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
