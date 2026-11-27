<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\PlatformUserResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** All users of the platform (gym owners and staff, super admins). */
class PlatformUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('مستخدم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المستخدمين');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('email')->label(__('البريد'))->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')->label(__('كلمة المرور'))->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state)),
                Toggle::make('is_active')->label(__('مفعل'))->default(true),
                Toggle::make('is_super_admin')->label(__('مدير المنصة'))
                    ->disabled(fn (?User $record) => $record?->id === auth()->id()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable(),
                TextColumn::make('email')->label(__('البريد'))->searchable(),
                TextColumn::make('gyms.name')->label(__('الجيمات'))->badge()->limitList(3),
                IconColumn::make('is_super_admin')->label(__('مدير المنصة'))->boolean(),
                IconColumn::make('is_active')->label(__('مفعل'))->boolean(),
                TextColumn::make('created_at')->label(__('تاريخ التسجيل'))->date('Y-m-d'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlatformUsers::route('/')];
    }
}
