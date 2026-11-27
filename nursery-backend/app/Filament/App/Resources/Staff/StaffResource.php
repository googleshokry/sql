<?php

namespace App\Filament\App\Resources\Staff;

use App\Filament\App\Resources\Staff\Pages\ManageStaff;
use App\Models\Branch;
use App\Models\User;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Staff = users who are members of the current nursery with a staff role.
 * Users are shared across nurseries (many-to-many), so tenancy goes through nursery_user.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static ?string $tenantOwnershipRelationshipName = 'nurseries';

    protected static ?string $tenantRelationshipName = 'members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('app.staff_member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.staff');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Roles::NURSERY_ADMIN) ?? false;
    }

    public static function roleOptions(): array
    {
        return collect(Roles::STAFF)->mapWithKeys(fn ($r) => [$r => __("app.role_{$r}")])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('app.name'))->required(),
            TextInput::make('email')->label(__('app.email'))->email()->required(),
            TextInput::make('phone')->label(__('app.phone'))->tel(),
            TextInput::make('password')->label(__('app.password'))->password()->revealable()->minLength(8)
                ->helperText(__('app.password_existing_user_hint')),
            Select::make('role')->label(__('app.role'))->options(static::roleOptions())->required(),
            Select::make('branch_id')->label(__('app.branch'))
                ->options(fn () => Branch::all()->mapWithKeys(fn ($b) => [$b->id => $b->name])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->role(Roles::STAFF))
            ->columns([
                TextColumn::make('name')->label(__('app.name'))->searchable(),
                TextColumn::make('email')->label(__('app.email'))->searchable(),
                TextColumn::make('phone')->label(__('app.phone')),
                TextColumn::make('roles.name')->label(__('app.role'))->badge()
                    ->formatStateUsing(fn (string $state) => __("app.role_{$state}")),
            ])
            ->recordActions([
                Action::make('removeFromNursery')
                    ->label(__('app.remove'))->color('danger')->icon(Heroicon::OutlinedUserMinus)
                    ->requiresConfirmation()
                    ->hidden(fn (User $record) => $record->is(auth()->user()))
                    ->action(function (User $record) {
                        $record->syncRoles([]);
                        Filament::getTenant()->members()->detach($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
