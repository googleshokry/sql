<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Pages\Reports\AccountLedger;
use App\Filament\Resources\TreasuryResource\Pages;
use App\Models\Treasury;
use App\Support\Translate;
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
    use TranslatesResourceLabels;

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
            TextInput::make('name')->label(__('الاسم'))->required(),
            Select::make('type')->label(__('النوع'))->options(Translate::options(Treasury::TYPES))->default('cash')->required()->disabledOn('edit'),
            Select::make('branch_id')->label(__('الفرع'))->relationship('branch', 'name'),
            TextInput::make('opening_balance')->label(__('الرصيد الافتتاحي'))->numeric()->default(0),
            TextInput::make('bank_name')->label(__('اسم البنك')),
            TextInput::make('iban')->label(__('IBAN / رقم الحساب')),
            Toggle::make('is_default')->label(__('الخزينة الافتراضية')),
            Toggle::make('is_active')->label(__('نشطة'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable(),
                TextColumn::make('type')->label(__('النوع'))->badge()->formatStateUsing(fn ($state) => __(Treasury::TYPES[$state] ?? $state)),
                TextColumn::make('branch.name')->label(__('الفرع')),
                TextColumn::make('balance')->label(__('الرصيد الحالي'))->state(fn ($record) => $record->balance())->numeric(2)->weight('bold'),
                IconColumn::make('is_default')->label(__('افتراضي'))->boolean(),
                IconColumn::make('is_active')->label(__('نشطة'))->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('ledger')->label(__('حركة الخزينة'))->icon(Heroicon::OutlinedDocumentChartBar)->color('info')
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
