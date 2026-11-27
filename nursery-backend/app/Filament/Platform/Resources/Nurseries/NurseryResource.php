<?php

namespace App\Filament\Platform\Resources\Nurseries;

use App\Filament\Platform\Resources\Nurseries\Pages\CreateNursery;
use App\Filament\Platform\Resources\Nurseries\Pages\EditNursery;
use App\Filament\Platform\Resources\Nurseries\Pages\ListNurseries;
use App\Models\Nursery;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class NurseryResource extends Resource
{
    protected static ?string $model = Nursery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getModelLabel(): string
    {
        return __('app.nursery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.nurseries');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    /** Shared with CreateNursery, which appends the first-admin section. */
    public static function formComponents(): array
    {
        return [
            Section::make(__('app.name'))->columns(2)->schema([
                TextInput::make('name.ar')->label(__('app.name_ar'))->required()->maxLength(120)
                    ->live(onBlur: true),
                TextInput::make('name.en')->label(__('app.name_en'))->required()->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => blank($get('slug')) ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->label('Slug')->required()->alphaDash()->unique(ignoreRecord: true),
            ]),
            Section::make(__('app.subscription'))->columns(2)->schema([
                Select::make('plan_id')->label(__('app.plan'))->relationship('plan', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
                DateTimePicker::make('subscription_ends_at')->label(__('app.subscription_ends_at')),
                Select::make('status')->label(__('app.status'))->required()->default('active')
                    ->options(['active' => __('app.status_active'), 'suspended' => __('app.status_suspended')]),
                Select::make('default_locale')->label(__('app.default_language'))->default('ar')
                    ->options(['ar' => 'العربية', 'en' => 'English']),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name'))->searchable(),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('plan.name')->label(__('app.plan')),
                TextColumn::make('members_count')->counts('members')->label(__('app.users')),
                TextColumn::make('status')->label(__('app.status'))->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('subscription_ends_at')->label(__('app.subscription_ends_at'))->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => __('app.status_active'), 'suspended' => __('app.status_suspended')]),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNurseries::route('/'),
            'create' => CreateNursery::route('/create'),
            'edit' => EditNursery::route('/{record}/edit'),
        ];
    }
}
