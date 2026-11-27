<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\InstallmentPlanResource\Pages;
use App\Filament\Resources\InstallmentPlanResource\RelationManagers\InstallmentsRelationManager;
use App\Models\InstallmentPlan;
use App\Support\Translate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** التقسيط */
class InstallmentPlanResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = InstallmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'خطة تقسيط';

    protected static ?string $pluralModelLabel = 'التقسيط';

    protected static ?string $recordTitleAttribute = 'number';

    protected static array $roles = ['sales', 'accountant', 'manager'];

    public const STATUSES = ['active' => 'سارية', 'completed' => 'مكتملة', 'cancelled' => 'ملغاة'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                Select::make('customer_id')->label(__('العميل'))->relationship('customer', 'name')->searchable()->preload()->required(),
                Select::make('sales_invoice_id')->label(__('الفاتورة (اختياري)'))->relationship('salesInvoice', 'number', fn ($query) => $query->whereNotNull('posted_at'))->searchable()->preload(),
                TextInput::make('total_amount')->label(__('المبلغ الإجمالي'))->numeric()->required(),
                TextInput::make('down_payment')->label(__('المقدم'))->numeric()->default(0),
                TextInput::make('interest_percent')->label(__('نسبة الفائدة %'))->numeric()->default(0),
                TextInput::make('installments_count')->label(__('عدد الأقساط'))->numeric()->integer()->minValue(1)->required()->default(6),
                Select::make('period')->label(__('الدورية'))->options(['monthly' => __('شهري'), 'weekly' => __('أسبوعي')])->default('monthly')->required(),
                DatePicker::make('start_date')->label(__('تاريخ أول قسط'))->required()->default(now()->addMonth()->startOfMonth()),
                TextInput::make('guarantor_name')->label(__('اسم الضامن')),
                TextInput::make('guarantor_phone')->label(__('هاتف الضامن')),
                Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label(__('رقم الخطة'))->weight('bold'),
                TextEntry::make('customer.name')->label(__('العميل')),
                TextEntry::make('salesInvoice.number')->label(__('الفاتورة'))->placeholder('-'),
                TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(self::STATUSES[$state] ?? $state)),
                TextEntry::make('total_amount')->label(__('المبلغ'))->numeric(2),
                TextEntry::make('down_payment')->label(__('المقدم'))->numeric(2),
                TextEntry::make('financed')->label(__('إجمالي الأقساط'))->state(fn (InstallmentPlan $record) => $record->financedAmount())->numeric(2),
                TextEntry::make('paid')->label(__('المحصّل'))->state(fn (InstallmentPlan $record) => $record->paidAmount())->numeric(2)->color('success'),
                TextEntry::make('remaining')->label(__('المتبقي'))->state(fn (InstallmentPlan $record) => $record->financedAmount() - $record->paidAmount())->numeric(2)->color('danger')->weight('bold'),
                TextEntry::make('installments_count')->label(__('عدد الأقساط')),
                TextEntry::make('guarantor_name')->label(__('الضامن'))->placeholder('-'),
                TextEntry::make('guarantor_phone')->label(__('هاتف الضامن'))->placeholder('-'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('الرقم'))->searchable()->weight('bold'),
                TextColumn::make('customer.name')->label(__('العميل'))->searchable(),
                TextColumn::make('salesInvoice.number')->label(__('الفاتورة'))->placeholder('-'),
                TextColumn::make('total_amount')->label(__('المبلغ'))->numeric(2),
                TextColumn::make('installments_count')->label(__('الأقساط')),
                TextColumn::make('paid')->label(__('المحصّل'))->state(fn (InstallmentPlan $record) => $record->paidAmount())->numeric(2),
                TextColumn::make('next_due')->label(__('القسط القادم'))->state(fn (InstallmentPlan $record) => $record->installments()->whereColumn('paid_amount', '<', 'amount')->min('due_date'))->date('Y-m-d')
                    ->color(fn ($state) => $state && now()->gt($state) ? 'danger' : null),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(self::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['active' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'][$state] ?? 'gray'),
            ])
            ->filters([SelectFilter::make('status')->label(__('الحالة'))->options(Translate::options(self::STATUSES))])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make(), DeleteAction::make()->visible(fn (InstallmentPlan $record) => $record->paidAmount() == 0)]);
    }

    public static function getRelations(): array
    {
        return [InstallmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallmentPlans::route('/'),
            'create' => Pages\CreateInstallmentPlan::route('/create'),
            'view' => Pages\ViewInstallmentPlan::route('/{record}'),
        ];
    }
}
