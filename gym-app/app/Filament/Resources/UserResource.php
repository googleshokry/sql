<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/** Staff of the current gym. A user can work in several gyms with a different role in each. */
class UserResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = User::class;

    // Users relate to gyms through the gym_user pivot.
    protected static ?string $tenantOwnershipRelationshipName = 'gyms';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Admin;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('موظف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الموظفين والصلاحيات');
    }

    public static function canCreate(): bool
    {
        $gym = Tenancy::gym();

        return parent::canCreate() && (! $gym || $gym->withinLimit('max_staff', $gym->users()->count()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('email')->label(__('البريد (اسم الدخول)'))->email()->required()
                    ->rules(fn (?User $record) => [Rule::unique('users', 'email')->ignore($record?->id)])
                    ->helperText(__('لو الموظف مسجل في جيم آخر استخدم زر "إضافة موظف موجود".')),
                TextInput::make('phone')->label(__('الموبايل'))->tel(),
                Select::make('role')->label(__('الصلاحية'))->options(User::roles())->required()
                    ->default(User::ROLE_RECEPTIONIST)
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $record && $component->state($record->roleIn())),
                TextInput::make('password')->label(__('كلمة المرور'))->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? __('اتركها فارغة للإبقاء على كلمة المرور الحالية') : null),
                Toggle::make('is_active')->label(__('مفعل'))->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable(),
                TextColumn::make('email')->label(__('البريد'))->searchable(),
                TextColumn::make('phone')->label(__('الموبايل')),
                TextColumn::make('role')->label(__('الصلاحية'))->badge()
                    ->state(fn (User $r) => $r->roleIn())
                    ->formatStateUsing(fn ($state) => User::roles()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'admin' => 'danger', 'manager' => 'warning', default => 'info' }),
                IconColumn::make('is_active')->label(__('مفعل'))->boolean(),
                TextColumn::make('created_at')->label(__('تاريخ الإضافة'))->date('Y-m-d'),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (User $record, array $data) {
                        $role = $data['role'];
                        unset($data['role']);
                        $record->update($data);
                        $record->gyms()->updateExistingPivot(Tenancy::id(), ['role' => $role]);

                        return $record;
                    }),
                Action::make('remove')->label(__('إزالة من الجيم'))->icon(Heroicon::OutlinedUserMinus)->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (User $r) => $r->id === auth()->id() || $r->id === Tenancy::gym()?->owner_id)
                    ->action(function (User $record) {
                        $record->gyms()->detach(Tenancy::id());
                        $record->tokens()->where('abilities', 'like', '%"gym:'.Tenancy::id().'"%')->delete();
                        Notification::make()->title(__('تمت إزالة الموظف من الجيم'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
