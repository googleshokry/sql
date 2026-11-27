<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
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
            Select::make('employee_id')->label('الموظف')->relationship('employee', 'name')->searchable()->preload()->required(),
            DatePicker::make('date')->label('التاريخ')->default(now())->required(),
            TextInput::make('amount')->label('مبلغ السلفة')->numeric()->required()->minValue(1),
            TextInput::make('monthly_deduction')->label('القسط الشهري من الراتب')->numeric()->default(0)->helperText('0 = تخصم بالكامل من أول راتب'),
            Select::make('treasury_id')->label('صرفت من')->relationship('treasury', 'name')->required(),
            TextInput::make('notes')->label('ملاحظات'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label('الموظف')->searchable(),
                TextColumn::make('date')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('amount')->label('المبلغ')->numeric(2),
                TextColumn::make('deducted_amount')->label('المخصوم')->numeric(2),
                TextColumn::make('remaining')->label('المتبقي')->state(fn ($record) => $record->remaining())->numeric(2)->color('danger'),
                TextColumn::make('treasury.name')->label('الخزينة'),
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
