<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Support\Labels;
use App\Models\Payroll;
use App\Models\Treasury;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as EntryColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** مسيرات الرواتب */
class PayrollResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'الموارد البشرية';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مسير رواتب';

    protected static ?string $pluralModelLabel = 'مسيرات الرواتب';

    protected static array $roles = ['hr', 'accountant', 'manager'];

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
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('month')->label(__('الشهر (YYYY-MM)'))->required()->default(now()->format('Y-m'))->regex('/^\d{4}-\d{2}$/')->disabledOn('edit'),
                Select::make('treasury_id')->label(__('صرف من'))->options(fn () => Treasury::query()->pluck('name', 'id'))
                    ->default(fn () => Treasury::query()->where('type', 'bank')->value('id')),
            ]),
            Section::make(__('بنود المسير'))->columnSpanFull()->visibleOn('edit')->schema([
                Repeater::make('items')->hiddenLabel()->relationship()->addable(false)->deletable(true)
                    ->table([
                        TableColumn::make(__('الموظف')), TableColumn::make(__('الأساسي')), TableColumn::make(__('البدلات')), TableColumn::make(__('العمولات')),
                        TableColumn::make(__('مكافآت')), TableColumn::make(__('خصومات')), TableColumn::make(__('خصم سلف')),
                    ])
                    ->schema([
                        Select::make('employee_id')->hiddenLabel()->relationship('employee', 'name')->disabled(),
                        TextInput::make('basic_salary')->hiddenLabel()->numeric(),
                        TextInput::make('allowances')->hiddenLabel()->numeric(),
                        TextInput::make('commissions')->hiddenLabel()->numeric(),
                        TextInput::make('bonuses')->hiddenLabel()->numeric(),
                        TextInput::make('deductions')->hiddenLabel()->numeric(),
                        TextInput::make('advance_deduction')->hiddenLabel()->numeric(),
                    ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label(__('الرقم')),
                TextEntry::make('month')->label(__('الشهر')),
                TextEntry::make('treasury.name')->label(__('الخزينة'))->placeholder('-'),
                TextEntry::make('total')->label(__('صافي الرواتب'))->numeric(2)->weight('bold'),
                TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(Labels::STATUS[$state] ?? $state))->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
            ]),
            RepeatableEntry::make('items')->label(__('الموظفين'))->columnSpanFull()
                ->table([
                    EntryColumn::make(__('الموظف')), EntryColumn::make(__('الأساسي')), EntryColumn::make(__('البدلات')), EntryColumn::make(__('العمولات')),
                    EntryColumn::make(__('مكافآت')), EntryColumn::make(__('خصومات')), EntryColumn::make(__('سلف')), EntryColumn::make(__('الصافي')),
                ])
                ->schema([
                    TextEntry::make('employee.name'),
                    TextEntry::make('basic_salary')->numeric(2),
                    TextEntry::make('allowances')->numeric(2),
                    TextEntry::make('commissions')->numeric(2),
                    TextEntry::make('bonuses')->numeric(2),
                    TextEntry::make('deductions')->numeric(2),
                    TextEntry::make('advance_deduction')->numeric(2),
                    TextEntry::make('net_salary')->numeric(2)->weight('bold'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('الرقم')),
                TextColumn::make('month')->label(__('الشهر'))->sortable(),
                TextColumn::make('items_count')->label(__('عدد الموظفين'))->counts('items'),
                TextColumn::make('total')->label(__('صافي الرواتب'))->numeric(2),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => __(Labels::STATUS[$state] ?? $state))->color(fn ($state) => Labels::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->defaultSort('month', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'view' => Pages\ViewPayroll::route('/{record}'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
