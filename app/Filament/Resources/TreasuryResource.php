<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Pages\Reports\AccountLedger;
use App\Filament\Resources\TreasuryResource\Pages;
use App\Models\Treasury;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TreasuryResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Treasury::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'خزينة / بنك';

    protected static ?string $pluralModelLabel = 'الخزائن والبنوك';

    protected static array $roles = ['accountant', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('الاسم')->required(),
            Select::make('type')->label('النوع')->options(Treasury::TYPES)->default('cash')->required()->disabledOn('edit'),
            Select::make('branch_id')->label('الفرع')->relationship('branch', 'name'),
            TextInput::make('opening_balance')->label('الرصيد الافتتاحي')->numeric()->default(0),
            TextInput::make('bank_name')->label('اسم البنك'),
            TextInput::make('iban')->label('IBAN / رقم الحساب'),
            Toggle::make('is_default')->label('الخزينة الافتراضية'),
            Toggle::make('is_active')->label('نشطة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('type')->label('النوع')->badge()->formatStateUsing(fn ($state) => Treasury::TYPES[$state] ?? $state),
                TextColumn::make('branch.name')->label('الفرع'),
                TextColumn::make('balance')->label('الرصيد الحالي')->state(fn ($record) => $record->balance())->numeric(2)->weight('bold'),
                IconColumn::make('is_default')->label('افتراضي')->boolean(),
                IconColumn::make('is_active')->label('نشطة')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('ledger')->label('حركة الخزينة')->icon(Heroicon::OutlinedDocumentChartBar)->color('info')
                    ->url(fn (Treasury $record) => AccountLedger::getUrl(['account' => $record->account_id])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTreasuries::route('/'),
        ];
    }
}
