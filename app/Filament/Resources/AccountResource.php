<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Pages\Reports\AccountLedger;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use App\Services\Accounting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** دليل الحسابات (شجرة الحسابات) */
class AccountResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'المحاسبة';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'حساب';

    protected static ?string $pluralModelLabel = 'دليل الحسابات';

    protected static array $roles = ['accountant'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('parent_id')->label('الحساب الرئيسي')
                ->options(fn () => Account::query()->where('is_group', true)->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"]))
                ->searchable()->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($parent = Account::find($state)) {
                        $set('type', $parent->type);
                        $set('code', Accounting::nextChildCode($parent));
                    }
                }),
            TextInput::make('code')->label('رقم الحساب')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('اسم الحساب')->required(),
            Select::make('type')->label('النوع')->options(Account::TYPES)->required()->disabled(fn (Get $get) => filled($get('parent_id')))->dehydrated(),
            Toggle::make('is_group')->label('حساب رئيسي (تجميعي)')->helperText('الحسابات الرئيسية لا تقبل قيوداً مباشرة'),
            Textarea::make('description')->label('الوصف')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('رقم الحساب')->searchable()->sortable()
                    ->formatStateUsing(fn ($state) => str_repeat('— ', min(max(strlen($state) - 1, 0), 3)).$state),
                TextColumn::make('name')->label('اسم الحساب')->searchable()->weight(fn ($record) => $record->is_group ? 'bold' : null),
                TextColumn::make('type')->label('النوع')->badge()->formatStateUsing(fn ($state) => Account::TYPES[$state] ?? $state),
                TextColumn::make('parent.name')->label('الحساب الرئيسي')->toggleable(),
                TextColumn::make('balance')->label('الرصيد')->state(fn (Account $record) => $record->balance())->numeric(2),
                IconColumn::make('is_system')->label('نظامي')->boolean()->toggleable(),
            ])
            ->filters([SelectFilter::make('type')->label('النوع')->options(Account::TYPES)])
            ->defaultSort('code')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->recordActions([
                Action::make('ledger')->label('كشف حساب')->icon(Heroicon::OutlinedDocumentChartBar)->color('info')
                    ->url(fn (Account $record) => AccountLedger::getUrl(['account' => $record->id])),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Account $record) => ! $record->is_system),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccounts::route('/')];
    }
}
