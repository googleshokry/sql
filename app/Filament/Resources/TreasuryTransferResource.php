<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\TreasuryTransferResource\Pages;
use App\Models\TreasuryTransfer;
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

class TreasuryTransferResource extends Resource
{
    use HasRoleAccess;
    use TranslatesResourceLabels;

    protected static ?string $model = TreasuryTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'تحويل بين الخزائن';

    protected static ?string $pluralModelLabel = 'التحويل بين الخزائن';

    protected static array $roles = ['accountant', 'manager'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->label(__('التاريخ'))->default(now())->required(),
            TextInput::make('amount')->label(__('المبلغ'))->numeric()->required()->minValue(0.01),
            Select::make('from_treasury_id')->label(__('من'))->relationship('from', 'name')->required()->different('to_treasury_id'),
            Select::make('to_treasury_id')->label(__('إلى'))->relationship('to', 'name')->required(),
            TextInput::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('الرقم'))->searchable(),
                TextColumn::make('date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('from.name')->label(__('من')),
                TextColumn::make('to.name')->label(__('إلى')),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2),
                TextColumn::make('notes')->label(__('ملاحظات')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTreasuryTransfers::route('/'),
        ];
    }
}
