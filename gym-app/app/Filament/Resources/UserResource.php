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

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمين والصلاحيات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('email')->label('البريد (اسم الدخول)')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('الموبايل')->tel(),
                Select::make('role')->label('الصلاحية')->options(User::ROLES)->required()->default(User::ROLE_RECEPTIONIST),
                TextInput::make('password')->label('كلمة المرور')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية' : null),
                Toggle::make('is_active')->label('مفعل')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable(),
                TextColumn::make('phone')->label('الموبايل'),
                TextColumn::make('role')->label('الصلاحية')->badge()
                    ->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'admin' => 'danger', 'manager' => 'warning', default => 'info' }),
                ToggleColumn::make('is_active')->label('مفعل')
                    ->disabled(fn (User $r) => $r->id === auth()->id()),
                TextColumn::make('created_at')->label('تاريخ الإضافة')->date('Y-m-d'),
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
