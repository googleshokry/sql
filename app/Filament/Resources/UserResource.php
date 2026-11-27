<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Tenancy;
use App\Support\Translate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمين والصلاحيات';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('company_id', Tenancy::id());
    }

    /** Respects the plan's user limit. */
    public static function canCreate(): bool
    {
        $company = Tenancy::company();

        return $company !== null && $company->canAdd('users', User::query()->where('company_id', $company->id)->count());
    }

    public static function canDelete(Model $record): bool
    {
        return $record->getKey() !== auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('الاسم'))->required(),
            TextInput::make('email')->label(__('البريد الإلكتروني'))->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label(__('كلمة المرور'))->password()->revealable()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)),
            Select::make('role')->label(__('الدور / الصلاحية'))->options(Translate::options(User::ROLES))->required()->default('sales'),
            Select::make('branch_id')->label(__('الفرع'))->relationship('branch', 'name'),
            Toggle::make('is_active')->label(__('نشط'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable(),
                TextColumn::make('email')->label(__('البريد'))->searchable(),
                TextColumn::make('role')->label(__('الدور'))->badge()->formatStateUsing(fn ($state) => __(User::ROLES[$state] ?? $state)),
                TextColumn::make('branch.name')->label(__('الفرع')),
                IconColumn::make('is_active')->label(__('نشط'))->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
