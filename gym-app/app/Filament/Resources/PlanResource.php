<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Models\Setting;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Memberships;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('باقة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الباقات');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('اسم الباقة'))->required()->maxLength(255)->columnSpan(2),
                ColorPicker::make('color')->label(__('اللون')),
                TextInput::make('duration_days')->label(__('المدة (بالأيام)'))->numeric()->minValue(1)->required()->default(30),
                TextInput::make('price')->label(__('السعر'))->numeric()->minValue(0)->required()->suffix(Setting::currency()),
                TextInput::make('sessions_count')->label(__('عدد الحصص'))->numeric()->minValue(1)
                    ->helperText(__('اتركه فارغاً لعدد غير محدود')),
                TextInput::make('freeze_days')->label(__('أيام التجميد المسموحة'))->numeric()->minValue(0)->default(0),
                TextInput::make('guest_invitations')->label(__('عدد الدعوات (ضيوف)'))->numeric()->minValue(0)->default(0),
                Toggle::make('includes_trainer')->label(__('تشمل مدرب خاص'))->inline(false),
                Textarea::make('description')->label(__('الوصف'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('مفعلة'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label(__('الباقة'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('duration_days')->label(__('المدة'))->suffix(__(' يوم'))->sortable(),
                TextColumn::make('price')->label(__('السعر'))->numeric(2, locale: 'en')->suffix(' '.Setting::currency())->sortable(),
                TextColumn::make('sessions_count')->label(__('الحصص'))->placeholder(__('غير محدود')),
                TextColumn::make('freeze_days')->label(__('أيام التجميد')),
                TextColumn::make('guest_invitations')->label(__('دعوات')),
                IconColumn::make('includes_trainer')->label(__('مدرب'))->boolean(),
                TextColumn::make('subscriptions_count')->label(__('عدد الاشتراكات'))->counts('subscriptions')->badge(),
                ToggleColumn::make('is_active')->label(__('مفعلة')),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
