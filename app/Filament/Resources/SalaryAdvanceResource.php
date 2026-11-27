<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\SalaryAdvanceResource\Pages;
use App\Models\SalaryAdvance;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SalaryAdvanceResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = SalaryAdvance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'الموارد البشرية';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'سلفة';

    protected static ?string $pluralModelLabel = 'السلف';

    protected static array $roles = ['hr', 'accountant', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')->label(__('الموظف'))->relationship('employee', 'name')->searchable()->preload()->required(),
            DatePicker::make('date')->label(__('التاريخ'))->default(now())->required(),
            TextInput::make('amount')->label(__('مبلغ السلفة'))->numeric()->required()->minValue(1),
            TextInput::make('monthly_deduction')->label(__('القسط الشهري من الراتب'))->numeric()->default(0)->helperText(__('0 = تخصم بالكامل من أول راتب')),
            Select::make('treasury_id')->label(__('صرفت من'))->relationship('treasury', 'name')->required(),
            TextInput::make('notes')->label(__('ملاحظات')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label(__('الموظف'))->searchable(),
                TextColumn::make('date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2),
                TextColumn::make('deducted_amount')->label(__('المخصوم'))->numeric(2),
                TextColumn::make('remaining')->label(__('المتبقي'))->state(fn ($record) => $record->remaining())->numeric(2)->color('danger'),
                TextColumn::make('treasury.name')->label(__('الخزينة')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSalaryAdvances::route('/'),
        ];
    }
}
