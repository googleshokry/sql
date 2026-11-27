<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Admin;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('مستخدم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المستخدمين والصلاحيات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('email')->label(__('البريد (اسم الدخول)'))->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label(__('الموبايل'))->tel(),
                Select::make('role')->label(__('الصلاحية'))->options(User::roles())->required()->default(User::ROLE_RECEPTIONIST),
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
                    ->formatStateUsing(fn ($state) => User::roles()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'admin' => 'danger', 'manager' => 'warning', default => 'info' }),
                ToggleColumn::make('is_active')->label(__('مفعل'))
                    ->disabled(fn (User $r) => $r->id === auth()->id()),
                TextColumn::make('created_at')->label(__('تاريخ الإضافة'))->date('Y-m-d'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $r) => $r->id === auth()->id()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
