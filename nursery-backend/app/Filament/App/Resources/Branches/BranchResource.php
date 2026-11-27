<?php

namespace App\Filament\App\Resources\Branches;

use App\Filament\App\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tenant-scoped through Nursery::branches() (Filament tenancy) AND the BelongsToNursery global scope.
 */
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function getModelLabel(): string
    {
        return __('app.branch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.branches');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(\App\Support\Roles::NURSERY_ADMIN) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name.ar')->label(__('app.name_ar'))->required()->maxLength(120),
            TextInput::make('name.en')->label(__('app.name_en'))->required()->maxLength(120),
            TextInput::make('phone')->label(__('app.phone'))->tel(),
            TextInput::make('address')->label(__('app.address'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name'))->searchable(),
                TextColumn::make('phone')->label(__('app.phone')),
                TextColumn::make('address')->label(__('app.address'))->limit(40),
            ])
            ->recordActions([
                EditAction::make()->mutateRecordDataUsing(fn (array $data, Branch $record) => [...$data, 'name' => $record->getTranslations('name')]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBranches::route('/')];
    }
}
